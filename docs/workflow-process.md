# Workflow Process

This page describes the intended finance workflow for Fachschaften and the AStA
FSR Buchhaltung. The workflow status is stored separately from the WordPress
post status; WordPress records stay published so Pods frontend lists can render
them.

## Actors

| Actor | Role in the process |
|-------|---------------------|
| Fachschaft finance | Creates and edits records for exactly one Fachschaft |
| Fachschaft reader | Reads records for exactly one Fachschaft |
| AStA FSR Buchhaltung | Reviews Zahlungsanweisungen across Fachschaften and marks them as executed |
| Auditor | Reads records across Fachschaften |

The AStA FSR Buchhaltung may not modify Beschlüsse. Beschlüsse stay under the
responsibility of the Fachschaft finance role.

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

Zahlungsanweisungen are the payment workflow objects. They must reference one
approved Beschluss before they can be submitted.

Allowed Zahlungsanweisung statuses:

| Status | Meaning | Set by |
|--------|---------|--------|
| Entwurf | The Fachschaft is still preparing the Zahlungsanweisung | Fachschaft finance |
| Eingereicht | The Fachschaft has submitted the Zahlungsanweisung for AStA review | Fachschaft finance |
| Rückfrage | AStA FSR Buchhaltung needs clarification before execution | AStA FSR Buchhaltung |
| Stoniert | The Fachschaft has cancelled the Zahlungsanweisung before execution | Fachschaft finance |
| Ausgeführt | The payment was executed and the workflow is closed | AStA FSR Buchhaltung |

Rules:

- New Zahlungsanweisungen start as `Entwurf`.
- Fachschaft finance may edit a Zahlungsanweisung while it is not
  `Ausgeführt`.
- Fachschaft finance may submit a prepared Zahlungsanweisung with `Einreichen`.
- `Einreichen` is only allowed if the Zahlungsanweisung references a Beschluss
  with status `Genehmigt`.
- Fachschaft finance may set `Stoniert` before the Zahlungsanweisung is
  `Ausgeführt`.
- Zahlungsanweisungen cannot be rejected. Clarification happens through
  `Rückfrage`.
- The actual question/answer exchange for `Rückfrage` is outside the portal for
  now and will be handled by e-mail.
- Only AStA FSR Buchhaltung may set `Rückfrage`.
- Only AStA FSR Buchhaltung may set `Ausgeführt`.
- `Ausgeführt` is terminal. After a Zahlungsanweisung is `Ausgeführt`, no
  further normal portal edit action is shown.
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
Entwurf -> Stoniert
Rückfrage -> Eingereicht
Rückfrage -> Stoniert
Eingereicht -> Rückfrage
Eingereicht -> Ausgeführt
```

`Ausgeführt` has no outgoing transition.
