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

AStA/global users can also use:

- `/dashboard/beschluesse/`
- `/dashboard/zahlungsanweisungen/`

These global pages are restricted to AStA, auditor, and admin roles. Fachschaft
users do not see global links and cannot view global page content directly.

## List and Detail

List pages use Pods shortcodes against the scoped post type for the selected
Fachschaft. Entries show status, amount, a stable internal ID, and row actions.
The generated list pages include a search box, status filter, and pagination so
the portal remains readable once the record set grows. Each row links to a
restricted detail page, and editable rows also link to a frontend edit page with
the record ID in the query string, for example
`/dashboard/informatik/beschluss-bearbeiten/?id=123`.
Direct public record routes for the workflow post types are disabled, so users
view records through the restricted portal pages.

## Create and Edit

Create pages use Pods frontend forms and create published workflow records so
Pods frontend lists can render them immediately. The visible workflow state is
the `draft`, `submitted`, `approved`, or `archived` value in the status field,
not the WordPress post status.

Edit/archive is modeled as status-driven workflow work. Finance roles use the
role-gated “Bearbeiten” control on the list page, which opens the dedicated
frontend edit page for the selected item. The edit page stays inside the portal
and loads the target record from the `?id=` query parameter, so no wp-admin UI
is involved. Readers do not see create/edit controls. Hard delete remains
outside the normal portal workflow.

## Status Values

Beschlüsse use:

- `draft`
- `submitted`
- `correction_requested`
- `approved`
- `rejected`
- `archived`

Zahlungsanweisungen use:

- `draft`
- `submitted`
- `approved`
- `rejected`
- `archived`
