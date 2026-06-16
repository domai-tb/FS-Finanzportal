# Workflow Process

This page describes the intended finance workflow for Fachschaften and the AStA
FSR Buchhaltung. The workflow status is stored separately from the WordPress
post status; WordPress records stay published so Pods frontend lists can render
them.

The generated portal currently expresses these rules through role-gated pages
and forms, setup-time normalization, browser-side validation, and verification.
There is no custom runtime state-machine hook enforcing transitions inside
WordPress request handling.

## Actors

| Actor | Role in the process |
|-------|---------------------|
| Fachschaft finance | Creates and edits records for exactly one Fachschaft |
| Fachschaft reader | Reads records for exactly one Fachschaft |
| AStA FSR Buchhaltung | Reviews Zahlungsanweisungen across Fachschaften and marks them as executed |
| Auditor | Reads records across Fachschaften |

The AStA FSR Buchhaltung may not modify Beschlüsse within the generated portal.
Beschlüsse stay under the responsibility of the Fachschaft finance role.

## Beschlüsse

Beschlüsse are independent from Zahlungsanweisungen and are owned by the
Fachschaft finance role of the owning Fachschaft. They are editable while they
are `Entwurf`. After a Beschluss is `Genehmigt` or `Abgelehnt`, the generated
portal no longer offers the normal edit action.

Allowed Beschluss statuses:

| Status | Meaning | Set by |
|--------|---------|--------|
| Entwurf | The Beschluss is still being recorded or corrected | Fachschaft finance |
| Genehmigt | The Beschluss was accepted by the Fachschaft | Fachschaft finance |
| Abgelehnt | The Beschluss was rejected by the Fachschaft | Fachschaft finance |

Rules:

- New Beschlüsse start as `Entwurf`.
- Beschlüsse are never `Eingereicht`.
- AStA FSR Buchhaltung has no write action for Beschlüsse.
- `Genehmigt` and `Abgelehnt` are terminal in the generated portal workflow.
- A Zahlungsanweisung can only be linked to a Beschluss with status
  `Genehmigt`.
- Decision metadata is stored as domain data: `decided_at`, `decided_by`, and
  `decision_note`. Users set these fields explicitly in the Beschluss workflow
  form.

## Zahlungsanweisungen

Zahlungsanweisungen are the payment workflow objects. They use one shared status
workflow for two payment types:

| Type | Meaning | Beschluss reference |
|------|---------|---------------------|
| `standard` | Normal payment against an approved Beschluss and invoice context | Required |
| `vorkasse` | Advance payment without a Beschluss reference | Empty |

Vorkasse payments also record a delivery method:

| Method | Meaning |
|--------|---------|
| `bar` | Cash disbursement |
| `ueberweisung` | Bank transfer; recipient/account details are required in the generated form |

Allowed Zahlungsanweisung statuses:

| Status | Meaning | Set by |
|--------|---------|--------|
| Entwurf | The Fachschaft is still preparing the Zahlungsanweisung | Fachschaft finance |
| Eingereicht | The Fachschaft has submitted the Zahlungsanweisung for AStA review | Fachschaft finance |
| Rückfrage | AStA FSR Buchhaltung needs clarification before execution | AStA FSR Buchhaltung |
| Storniert | The Fachschaft has cancelled the Zahlungsanweisung before execution | Fachschaft finance |
| Ausgeführt | The payment was executed and the workflow is closed | AStA FSR Buchhaltung |

Rules:

- New Zahlungsanweisungen start as `Entwurf`.
- Fachschaft finance may edit a Zahlungsanweisung while it is not
  `Ausgeführt`.
- Fachschaft finance may submit a prepared Zahlungsanweisung with `Einreichen`.
- `Einreichen` for standard payments is only allowed if the Zahlungsanweisung
  references a Beschluss with status `Genehmigt`.
- `Einreichen` for Vorkasse payments is allowed once the amount, purpose,
  delivery method, and Vorkasse justification are complete. Bank-transfer
  Vorkasse also needs recipient/account details.
- Vorkasse payments are not included in Beschluss open-budget calculations.
- Fachschaft finance may set `Storniert` before the Zahlungsanweisung is
  `Ausgeführt`.
- Zahlungsanweisungen cannot be rejected. Clarification happens through
  `Rückfrage`.
- The generated portal stores one structured Rückfrage request and one
  structured response on the Zahlungsanweisung. Follow-up discussion can still
  happen by e-mail when needed.
- The payment detail page also offers setup-generated notification drafts for
  the common workflow events so handoffs stay visible without runtime mail
  automation.
- Within the generated portal, only AStA FSR Buchhaltung is shown controls to
  set `Rückfrage`.
- Within the generated portal, only AStA FSR Buchhaltung is shown controls to
  set `Ausgeführt`.
- `Ausgeführt` is terminal in the generated portal. After a Zahlungsanweisung
  is `Ausgeführt`, no further normal portal edit action is shown.
- Workflow metadata is stored as domain data: `submitted_at`, `reviewed_at`,
  `reviewed_by`, `executed_at`, `executed_by`, and `workflow_note`. Users set
  these fields explicitly in the role-gated workflow forms.

## Workflow Log

The user-facing workflow log is a generated detail-page table built from the
domain workflow fields above. It is not a generic audit-log viewer.

Each Beschluss detail page shows:

- `Erstellt`
- `Entscheidung`

Each Zahlungsanweisung detail page shows:

- `Erstellt`
- `Eingereicht`
- `Rückfrage`
- `Antwort`
- `Geprüft`
- `Ausgeführt`

The columns are `Schritt`, `Status`, `Datum`, `Person`, and `Hinweis`. Meta
Ledger still records post-meta changes in the background for administrators,
but it is not used as the portal's visible workflow log because it does not
provide a scoped frontend shortcode for portal users.

## Process Summary

For Beschlüsse:

```text
Entwurf -> Genehmigt
Entwurf -> Abgelehnt
```

`Genehmigt` and `Abgelehnt` have no outgoing transition in the generated portal.

For Zahlungsanweisungen:

```text
Entwurf -> Eingereicht
Entwurf -> Storniert
Rückfrage -> Eingereicht
Rückfrage -> Storniert
Eingereicht -> Rückfrage
Eingereicht -> Ausgeführt
```

`Ausgeführt` has no outgoing transition.
