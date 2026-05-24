# Frontend Workflows

The portal is organized under `/dashboard/`.

## Pages

Each Fachschaft gets:

- `/dashboard/<slug>/`
- `/dashboard/<slug>/beschluesse/`
- `/dashboard/<slug>/beschluss-details/`
- `/dashboard/<slug>/beschluss-erstellen/`
- `/dashboard/<slug>/beschluss-bearbeiten/`
- `/dashboard/<slug>/zahlungsanweisungen/`
- `/dashboard/<slug>/zahlungsanweisung-details/`
- `/dashboard/<slug>/zahlungsanweisung-erstellen/`
- `/dashboard/<slug>/zahlungsanweisung-bearbeiten/`

AStA finance/reviewer users and administrators can also use:

- `/dashboard/beschluesse/`
- `/dashboard/zahlungsanweisungen/`

These global pages are restricted to AStA finance/reviewer and admin roles.
They render one visible table per workflow type across all Fachschaften. The
table has global search, status filtering, Fachschaft filtering, and client-side
pagination over scoped Pods row shortcodes. This is an overview UI over the
separate scoped post types, not a shared runtime data model. Fachschaft users
and auditors do not see global links and cannot view global page content
directly.

The generated block header navigation includes `Beschlüsse` and
`Zahlungsanweisungen` links beside `Dashboard` and `Logout`. Those links are
plain navigation items styled by the active theme. A small setup-generated
browser script points them to the unified overview tables for AStA pages and to
the current Fachschaft's scoped workflow pages on Fachschaft pages.

## List and Detail

List pages use Pods shortcodes against the scoped post type for the selected
Fachschaft. Entries show status, amount, a stable internal ID, and row actions.
The generated list pages use the same client-side search, status filter, and
pagination controls as the AStA overview tables, but over exactly one scoped
post type. This keeps the Fachschaft and AStA list behavior consistent while
preserving physical data isolation. Each row links to a restricted detail page,
and editable rows also link to a frontend edit page with the record ID in the
query string, for example
`/dashboard/informatik/beschluss-bearbeiten/?id=123`.
Direct public record routes for the workflow post types are disabled, so users
view records through the restricted portal pages.
Generated page fragments, form field lists, portal CSS, and reusable browser
scripts are setup inputs under `wordpress/config/portal/`. The WP-CLI setup
modules render those files into WordPress pages and options; no runtime custom
PHP is loaded for these workflows.

## Create and Edit

Create pages use styled Pods frontend forms and create published workflow
records so Pods frontend lists can render them immediately. The visible
workflow state is stored in the domain status field, not the WordPress post
status. The intended status transitions are documented in
`docs/workflow-process.md`.

Edit and workflow actions are modeled as status-driven workflow work. Finance
roles use the role-gated “Bearbeiten” control for Beschlüsse and the
“Bearbeiten / Einreichen / Stornieren” control for Zahlungsanweisungen. AStA
review users see only the Zahlungsanweisung workflow control for `Rückfrage`
and `Ausgeführt`. The edit page stays inside the portal and loads the target
record from the `?id=` query parameter, so no wp-admin UI is involved. Readers
and auditors do not see create/edit controls. Hard delete remains outside the
normal portal workflow.

Create forms do not expose status fields. New records use the domain default
`Entwurf`; later status changes are made through the role-gated edit/workflow
forms. Zahlungsanweisungen have a `zahlungstyp`: `standard` or `vorkasse`.
Standard payments reference a scoped Beschluss relationship field instead of
free text. The setup and UI expect this Beschluss to be `Genehmigt`. Vorkasse
payments do not reference a Beschluss; they require a delivery method
(`bar` or `ueberweisung`) and a Vorkasse justification. Bank-transfer Vorkasse
payments also require recipient/account details.

The generated forms run basic browser-side checks before submit: non-empty
titles, positive amounts, useful purpose text, Beschluss dates that are not in
the future, a selected approved Beschluss for standard Zahlungsanweisungen, and
the conditional Vorkasse fields for advance payments. Zahlungsanweisung detail
pages link to the related Beschluss detail page and show the Beschluss budget
beside the Zahlungsanweisung amount for standard payments. For Vorkasse
payments, the detail page shows type, method, justification, and transfer
details where applicable. Beschluss detail pages derive the reverse view from
the Beschluss relationship and list all related standard Zahlungsanweisungen.
Both standard detail views calculate `Betrag Offen` as `Betrag Beschlossen`
minus the sum of all related standard Zahlungsanweisung amounts.
Zahlungsanweisung create and Fachschaft edit forms include generated budget
source data and disable submission when a standard payment exceeds the
currently open budget. Vorkasse payments skip this budget guard because they
are not tied to a Beschluss. This is a portal UX guard; hard transition and
budget enforcement would require a future server-side mechanism that still
respects the no-runtime-custom-PHP architecture constraint.

The visible workflow history is modeled as domain data, not as a generic audit
log. Beschluss workflow forms expose `decided_at`, `decided_by`, and
`decision_note`; Zahlungsanweisung workflow forms expose `submitted_at`,
`reviewed_at`, `reviewed_by`, `executed_at`, `executed_by`, and
`workflow_note`. Users set these date fields explicitly in the role-gated forms.
Detail pages render these fields as a unified `Workflow-Log` table at the end
of the entry page. The log is rendered through a named Pods template so the
shortcode itself is not visible in the page. Meta Ledger remains a background
audit mechanism for post-meta changes, but it is not the portal's visible
workflow history UI.

## Business Status Labels

Beschlüsse use:

- `Entwurf`
- `Genehmigt`
- `Abgelehnt`

Zahlungsanweisungen use:

- `Entwurf`
- `Eingereicht`
- `Rückfrage`
- `Stoniert`
- `Ausgeführt`
