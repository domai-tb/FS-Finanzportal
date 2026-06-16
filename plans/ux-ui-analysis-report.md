# FS Finanzportal UX/UI Analysis Report

This document records the current user experience assessment of the live portal
and translates the findings into concrete interface improvements.

## Scope

The review covers the visible login page, dashboard, list views, create/workflow
screens, and reporting pages in the current portal build.

## Overall Assessment

The portal is functional and structurally consistent, but it still reads like a
prototype from a design perspective. The visual system is clean, minimal, and
safe, yet it lacks stronger hierarchy, clearer workflow guidance, and a more
distinct finance-product identity.

The main UX risk is not complexity in the controls themselves. It is the amount
of table-heavy information shown without enough visual guidance, combined with a
reporting surface that currently undermines trust when values appear inconsistent
with the rest of the portal.

## Key Issues

### 1. Weak dashboard hierarchy

The dashboard is easy to read, but the page is sparse and the available actions
all sit at the same visual level. It behaves more like a link list than a true
workflow hub.

Impact:

- Users do not get a clear primary path.
- The page does not communicate role-specific next steps.
- The large empty space makes the page feel unfinished.

### 2. Dense list views

The list pages are useful because they provide search, filters, pagination, and
export. However, the presentation is still strongly table-centric and therefore
harder to scan than it should be.

Impact:

- Filter state is not visually prominent enough.
- Controls and results are too close together.
- Row scanning depends on reading many similar-looking lines.

### 3. Reporting trust problem

The reports page is the strongest concern. When summary cards show zeroed values
while the portal clearly contains records, the user can reasonably question the
accuracy of the page.

Impact:

- Summary data feels disconnected from list data.
- The reporting page loses credibility.
- Finance users need to verify data elsewhere instead of trusting the portal.

### 4. Abrupt access-denied states

When a user lands on a route they cannot access, the portal stops with a plain
message instead of guiding them to the correct destination.

Impact:

- The user has no recovery path.
- The error state does not explain the reason clearly enough.
- The screen does not preserve the portal’s otherwise calm tone.

### 5. Generic visual identity

The current styling is stable, but it does not yet feel like a finished product.
The typography, spacing, and action styling are functional rather than branded.

Impact:

- The portal feels more administrative than product-like.
- Key actions are not visually distinctive enough.
- The interface lacks a consistent sense of rhythm and emphasis.

## Recommended Fixes

### Dashboard

- Make one primary action visually dominant.
- Add short helper text under each dashboard entry.
- Reduce the empty vertical space so the page feels intentional.
- Add role-oriented cues that explain where different users should go first.

### List Views

- Separate filters, summary, and results more clearly.
- Improve hover or zebra states so rows are easier to scan.
- Keep actions visually anchored when horizontal scrolling occurs.
- Make the active filter scope more obvious.

### Reports

- Verify all aggregation logic used by the summary cards.
- Make empty states explicit when there is no data.
- Ensure reported totals always align with list counts and the current scope.
- Add context labels so users know whether values are global, Fachschaft-specific,
  or filtered.

### Access States

- Explain why access is blocked in plain language.
- Offer a direct link back to the correct Fachschaft or dashboard view.
- Keep the design consistent with the rest of the portal.

### Visual System

- Introduce a clearer typographic hierarchy.
- Use one consistent accent color for primary actions and state emphasis.
- Add more deliberate card depth and section framing.
- Tighten spacing rules so pages feel part of one system.

## Priority Plan

### P0

- Fix reporting inconsistencies.
- Repair trust-breaking summary states.
- Improve access-denied recovery paths.

### P1

- Strengthen dashboard hierarchy.
- Improve list scannability and filter clarity.

### P2

- Refine the overall brand feel, spacing, and typographic system.

## Practical Design Direction

The best next step is not a full redesign. It is a focused usability pass that
preserves the current WordPress setup while making the portal feel more guided,
more trustworthy, and more deliberate.

If the team only changes a few things first, the order should be:

1. Make the reports page truthful and context-aware.
2. Turn the dashboard into a real navigation hub.
3. Make list pages easier to scan.
4. Replace dead-end access states with recovery options.
5. Apply a more intentional visual system across the portal.