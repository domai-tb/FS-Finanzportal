import test from 'node:test';
import assert from 'node:assert/strict';

function parseAmount(value) {
  const cleaned = String(value ?? '').replace(/ /g, '').replace(/[^0-9,.-]/g, '');
  if (!cleaned) {
    return 0;
  }

  const comma = cleaned.lastIndexOf(',');
  const dot = cleaned.lastIndexOf('.');
  let normalized = cleaned;

  if (comma > dot) {
    normalized = normalized.replace(/[.]/g, '').replace(',', '.');
  } else if (dot > comma) {
    normalized = normalized.replace(/,/g, '');
  } else {
    normalized = normalized.replace(',', '.');
  }

  const parsed = Number.parseFloat(normalized);
  return Number.isFinite(parsed) ? parsed : 0;
}

function monthKey(date) {
  return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}`;
}

function summarize(rows) {
  const periods = new Map();

  function ensurePeriod(key, label) {
    if (!periods.has(key)) {
      periods.set(key, {
        label,
        beschlussCount: 0,
        beschlussAmount: 0,
        paymentCount: 0,
        paymentAmount: 0,
        executedCount: 0,
        executedAmount: 0,
        openCount: 0,
        openAmount: 0,
      });
    }

    return periods.get(key);
  }

  for (const row of rows) {
    const kind = row.kind ?? '';
    const status = row.status ?? '';
    const amount = parseAmount(row.amount);
    const dateSource =
      kind === 'zahlung' && status === 'executed'
        ? row.executed_at
        : row.report_date || row.submitted_at || row.reviewed_at || row.created_at;
    const date = dateSource ? new Date(`${dateSource}T00:00:00Z`) : null;
    const periodKey = date ? monthKey(date) : 'unbekannt';
    const periodLabel = date ? date.toLocaleString('de-DE', { month: 'long', year: 'numeric', timeZone: 'UTC' }) : 'Ohne Datum';
    const period = ensurePeriod(periodKey, periodLabel);

    if (kind === 'beschluss') {
      period.beschlussCount += 1;
      if (status === 'approved') {
        period.beschlussAmount += amount;
      } else if (status === 'draft') {
        period.openCount += 1;
        period.openAmount += amount;
      }
      continue;
    }

    if (kind === 'zahlung') {
      period.paymentCount += 1;
      period.paymentAmount += amount;

      if (status === 'executed') {
        period.executedCount += 1;
        period.executedAmount += amount;
      } else if (status === 'draft' || status === 'submitted' || status === 'correction_requested') {
        period.openCount += 1;
        period.openAmount += amount;
      }
    }
  }

  return Object.fromEntries(periods.entries());
}

test('executed payments bucket by execution date', () => {
  const summary = summarize([
    {
      kind: 'zahlung',
      status: 'executed',
      amount: '50,00 €',
      submitted_at: '2026-05-30',
      executed_at: '2026-06-12',
      created_at: '2026-05-30',
    },
  ]);

  assert.equal(summary['2026-06'].paymentCount, 1);
  assert.equal(summary['2026-06'].executedCount, 1);
  assert.equal(summary['2026-06'].executedAmount, 50);
  assert.equal(summary['2026-06'].openCount, 0);
  assert.equal(summary['2026-05'], undefined);
});

test('cancelled payments count once and do not leak into executed totals', () => {
  const summary = summarize([
    {
      kind: 'zahlung',
      status: 'cancelled',
      amount: '12,50 €',
      submitted_at: '2026-04-02',
      created_at: '2026-04-01',
    },
  ]);

  assert.equal(summary['2026-04'].paymentCount, 1);
  assert.equal(summary['2026-04'].paymentAmount, 12.5);
  assert.equal(summary['2026-04'].executedCount, 0);
  assert.equal(summary['2026-04'].openCount, 0);
});

test('open payments still use submission dates for the period table', () => {
  const summary = summarize([
    {
      kind: 'zahlung',
      status: 'submitted',
      amount: '9,99 €',
      report_date: '',
      submitted_at: '2026-05-02',
      reviewed_at: '2026-05-10',
      created_at: '2026-04-30',
    },
  ]);

  assert.equal(summary['2026-05'].paymentCount, 1);
  assert.equal(summary['2026-05'].openCount, 1);
  assert.equal(summary['2026-05'].openAmount, 9.99);
});
