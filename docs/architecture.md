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
| `wordpress/config/oidc/openid-connect-generic.settings.json` | OIDC plugin defaults |
| `wordpress/config/demo/beschluesse.json` | Seed Beschluss records |

The old runtime mu-plugin table shortcode has been removed. WordPress no longer
mounts `wordpress/mu-plugins` into the running container.

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
| `beschluss_status` | Workflow status |
| `belege` | Attachments |
| `zahlungsanweisung_ref` | Payment instruction reference |
| `notes` | Notes and correction requests |

Important Zahlungsanweisung fields:

| Field | Purpose |
|-------|---------|
| `fachschaft` | Stored Fachschaft slug for reporting and imports |
| `betrag` | Amount in EUR |
| `verwendungszweck` | Payment purpose |
| `zahlungs_status` | Workflow status |
| `belege` | Attachments |
| `beschluss_ref` | Beschluss reference |
| `notes` | Notes and correction requests |

## Access Decision

The portal deliberately avoids one shared `beschluss` table with dynamic
Keycloak-claim row filtering. That model is not reliably enforceable with only
WordPress.org plugins and no runtime PHP.

Instead, each Fachschaft has separate post types and separate WordPress roles.
WordPress capabilities enforce isolation before a record can be listed, read,
edited, or published. Members content permissions restrict the frontend portal
pages so Fachschaft users cannot view other Fachschaft pages or global overview
pages. Workflow records use published WordPress post status because Pods
frontend list shortcodes do not reliably render private workflow posts for
normal portal users. Direct public record routes are disabled, and workflow
state is tracked in the explicit status fields. The generated portal list views
use Pods search and pagination so they stay usable without custom runtime PHP.
Frontend edit pages are also generated through Pods forms and load the target
record from a query-string item ID, so the portal never needs the native
WordPress editor for normal workflows.

## WordPress Plugins

| Plugin | Used for |
|--------|----------|
| `daggerhart-openid-connect-generic` | Keycloak login |
| `pods` | Custom post types, fields, shortcodes, and frontend forms |
| `members` | Role/capability management and page-level content permissions |
| `content-control` | Installed for page-level access experiments |
| `publishpress-statuses` | Installed for workflow status expansion |
| `remove-dashboard-access-for-non-admins` | Blocks `wp-admin` except for roles with finance/edit workflow access |
| `hide-admin-bar-based-on-user-roles` | Hides the admin bar for portal roles |

## Verification

`./scripts/verify-setup.sh` checks that:

- required plugins are active
- no project-specific runtime mu-plugin is installed
- legacy generic workflow post types are not registered
- every configured Fachschaft has scoped Beschluss and Zahlungsanweisung types
- Fachschaft roles cannot access other Fachschaft pages or global pages
- global roles have the intended cross-Fachschaft access
- unknown users have no workflow capabilities
- generated Pods shortcodes do not use unsafe raw `orderby` SQL
- portal pages and demo records are idempotent
