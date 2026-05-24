# Architecture and Configuration

FS-Finanzportal is a local, reproducible WordPress workflow prototype. Runtime
behavior comes from WordPress, WordPress.org plugins, imported configuration,
and Keycloak. Project-specific PHP runs only in WP-CLI setup scripts.

## System Overview

```text
Browser
  |-- WordPress on http://localhost:8080
  |     |-- MariaDB stores WordPress data
  |     |-- Pods provides configured content types, fields, forms, and lists
  |     |-- Members restricts portal pages by role
  |     |-- Meta Ledger stores workflow post-meta audit history
  |     |-- Remove Dashboard Access blocks wp-admin for portal roles
  |     `-- OpenID Connect Generic redirects login to Keycloak
  |
  `-- Keycloak on http://localhost:8180
        `-- PostgreSQL stores realm, users, roles, groups, and clients
```

## Configuration Sources

| File | Purpose |
|------|---------|
| `.env` | Local secrets, URLs, ports, and database credentials |
| `compose.yaml` | Service definitions, volumes, health checks, and setup mounts |
| `keycloak/realms/fs-finance-realm.json` | Baseline Keycloak realm import |
| `wordpress/config/fachschaften.json` | Single source for Fachschaft slugs and labels |
| `wordpress/config/portal/pods.json` | Human-editable scoped Pods field schema and workflow pick values |
| `wordpress/config/portal/forms.json` | Human-editable frontend Pods form field lists |
| `wordpress/config/portal/roles.json` | Human-editable role grouping and portal access defaults |
| `wordpress/config/portal/templates/` | Setup-rendered block/HTML fragments for generated portal pages |
| `wordpress/config/portal/assets/` | Setup-injected portal CSS and browser scripts |
| `wordpress/config/oidc/openid-connect-generic.settings.json` | OIDC plugin defaults |
| `wordpress/config/demo/beschluesse.json` | Seed Beschluss records |
| `wordpress/config/demo/vorkasse.json` | Seed Vorkasse Zahlungsanweisung records |

The old runtime mu-plugin table shortcode has been removed. WordPress no longer
mounts `wordpress/mu-plugins` into the running container.
Project-specific PHP remains setup-only: WP-CLI entrypoints under
`scripts/wp-eval/` load small modules from `lib/`, `pods/`, and `portal/` to
translate the editable config files into WordPress database state.

## Content Model

The setup generates scoped Pods post types for every Fachschaft in
`wordpress/config/fachschaften.json`.

| Pattern | Example | Purpose |
|---------|---------|---------|
| `b_<fachschaft>` | `b_informatik` | Beschlüsse for exactly one Fachschaft |
| `za_<fachschaft>` | `za_informatik` | Zahlungsanweisungen for exactly one Fachschaft |
| `fachschaft` | `informatik` | Admin-only Fachschaft master data |

The short post type prefixes are intentional. WordPress post type keys must stay
short, while labels shown in the UI use the full German names.

Important Beschluss fields:

| Field | Purpose |
|-------|---------|
| `fachschaft` | Stored Fachschaft slug for reporting and imports |
| `beschlussdatum` | Date of the decision |
| `betrag` | Amount in EUR |
| `zweck_beschreibung` | Purpose and description |
| `beschluss_status` | Workflow status: `draft`, `approved`, `rejected` |
| `decided_at` | Date set by the user when the decision is approved or rejected |
| `decided_by` | Person/role recorded by the user for the decision |
| `decision_note` | Decision note |
| `belege` | Attachments |
| `notes` | Notes and correction requests |

Important Zahlungsanweisung fields:

| Field | Purpose |
|-------|---------|
| `fachschaft` | Stored Fachschaft slug for reporting and imports |
| `zahlungstyp` | Payment type: `standard` or `vorkasse` |
| `betrag` | Amount in EUR |
| `verwendungszweck` | Payment purpose |
| `vorkasse_method` | Vorkasse delivery method: `bar` or `ueberweisung` |
| `vorkasse_begruendung` | Justification for an advance payment |
| `empfaenger_details` | Recipient/account details for Vorkasse bank transfers |
| `zahlungs_status` | Workflow status: `draft`, `submitted`, `correction_requested`, `cancelled`, `executed` |
| `submitted_at` | Date set by the user when submitting the payment |
| `reviewed_at` | Date set by the reviewer when checking the payment |
| `reviewed_by` | Person/role recorded by the user for review |
| `clarification_requested_at`, `clarification_requested_by`, `clarification_request` | Structured AStA Rückfrage metadata |
| `clarification_answered_at`, `clarification_answered_by`, `clarification_response` | Structured Fachschaft response metadata |
| `executed_at` | Date set by the user when marking the payment executed |
| `executed_by` | Person/role recorded by the user for execution |
| `workflow_note` | Workflow note for submission, review, correction, or execution |
| `vendor_name`, `invoice_number`, `invoice_date` | Invoice and recipient completeness metadata |
| `belege` | Attachments |
| `beschluss_ref` | Single relationship to a scoped Beschluss; required by generated forms for standard payments and empty for Vorkasse |
| `notes` | Notes and correction requests |

Standard Zahlungsanweisungen reference an approved Beschluss through
`beschluss_ref`. Vorkasse Zahlungsanweisungen use the same scoped payment post
types and workflow statuses but have `zahlungstyp = vorkasse`, no Beschluss
reference, a delivery method, and a justification. Beschluss detail pages derive
the reverse one-to-many view from the `beschluss_ref` relationship and list all
related standard Zahlungsanweisungen. The Beschluss record itself does not
store a separate payment-reference text field. The generated detail pages
calculate the open budget in the browser from the rendered Pods data:
`Betrag Offen = Betrag Beschlossen - Summe der zugehörigen Standard-Zahlungsanweisungen`.
Vorkasse records are skipped by the generated budget source data because they
are not tied to a Beschluss budget.
The visible workflow log is built from these domain fields and rendered as one
table at the end of each detail page through named Pods templates. Meta Ledger
remains background audit storage, not the user-facing workflow history.

## Access Decision

The portal deliberately avoids one shared `beschluss` table with dynamic
Keycloak-claim row filtering. That model is not reliably enforceable with only
WordPress.org plugins and no runtime PHP.

Instead, each Fachschaft has separate post types and separate WordPress roles.
WordPress capabilities enforce isolation before a record can be listed, read,
edited, or published. Members content permissions restrict the frontend portal
pages so Fachschaft users cannot view other Fachschaft pages or AStA overview
pages. AStA overview pages are generated as one visible table per workflow type
with browser-side search, status filtering, Fachschaft filtering, and
pagination over scoped Pods row shortcodes. This keeps the physical
post-type isolation intact and does not introduce a runtime PHP query layer.
Workflow records use published WordPress post status because Pods
frontend list shortcodes do not reliably render private workflow posts for
normal portal users. Direct public record routes are disabled, and workflow
state is tracked in the explicit status fields. The generated portal list views
render scoped Pods row shortcodes into tables and use one client-side control
layer for search, status filtering, and pagination so Fachschaft and AStA views
behave consistently without custom runtime PHP.
Frontend edit pages are also generated through Pods forms and load the target
record from a query-string item ID, so the portal never needs the native
WordPress editor for normal workflows.

The setup normalizes legacy workflow status values into the current domain
status vocabulary. Status and other workflow field changes are recorded by the
free WordPress.org Meta Ledger plugin as post-meta history. Without custom
runtime PHP, transition rules are enforced by generated role-gated pages/forms,
capabilities, and verification rather than by a custom state-machine hook.
Automated workflow notifications are intentionally not configured in the
baseline. The free WordPress.org `notification` plugin was reviewed because it
supports email/webhook notifications and post/custom-field merge tags, but
precise workflow notifications for Pods meta transitions would still require
runtime trigger/condition integration or manual admin configuration that this
project intentionally avoids. Generated action queues, visible Rückfrage
fields, and contextual mail/contact affordances are the no-custom-runtime
fallback.

Docker image tags are pinned to explicit minor/patch lines in `compose.yaml`.
Update those tags deliberately during maintenance and re-run the full setup
verification after each image bump.

## WordPress Plugins

| Plugin | Used for |
|--------|----------|
| `daggerhart-openid-connect-generic` | Keycloak login |
| `pods` | Custom post types, fields, shortcodes, and frontend forms |
| `members` | Role/capability management and page-level content permissions |
| `content-control` | Installed for page-level access experiments |
| `publishpress-statuses` | Installed for workflow status expansion |
| `meta-ledger` | Audit history for workflow post meta changes |
| `remove-dashboard-access-for-non-admins` | Blocks `wp-admin` except for roles with finance/edit workflow access |
| `hide-admin-bar-based-on-user-roles` | Hides the admin bar for portal roles |

## Verification

`./scripts/verify-setup.sh` checks that:

- required plugins are active
- no project-specific runtime mu-plugin is installed
- legacy generic workflow post types are not registered
- every configured Fachschaft has scoped Beschluss and Zahlungsanweisung types
- Fachschaft roles cannot access other Fachschaft pages or AStA overview pages
- AStA roles can access unified overview pages, while auditors keep scoped read access only
- global roles have the intended cross-Fachschaft capability access
- unknown users have no workflow capabilities
- workflow statuses, workflow date fields, and Beschluss relationship fields
  match the documented process
- generated detail pages use a single `Workflow-Log` table and do not expose
  raw Pods shortcode text
- Meta Ledger is active and configured for all scoped workflow post types
- generated Pods shortcodes do not use unsafe raw `orderby` SQL
- portal pages and demo records are idempotent
