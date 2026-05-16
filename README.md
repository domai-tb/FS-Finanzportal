# FS-Finanzportal

FS-Finanzportal is a Docker-based WordPress prototype for Fachschaft finance
workflows. It helps student councils manage Beschlüsse, Belege, and
Zahlungsanweisungen before AStA finance performs final accounting.

The project is intentionally low-code. Runtime behavior comes from WordPress,
WordPress.org plugins, imported configuration, and Keycloak. Project-specific
PHP is limited to WP-CLI setup scripts; no custom WordPress plugin or custom
runtime PHP is mounted into the running site.

## What It Contains

| Area | Implementation |
|------|----------------|
| Application | WordPress 6 with Apache/PHP |
| WordPress database | MariaDB 11 |
| Login / SSO | Keycloak 26.2 with OpenID Connect |
| Keycloak database | PostgreSQL 16 |
| Content model | Pods post types generated from `wordpress/config/fachschaften.json` |
| Access control | Fachschaft-scoped WordPress roles, capabilities, and Members page permissions |
| Audit history | Meta Ledger post-meta history for workflow field changes |
| Access hardening | Remove Dashboard Access and Hide Admin Bar |
| Setup automation | Docker Compose and WP-CLI |
| Demo content | JSON data in `wordpress/config/demo/` |

## Quick Start

```bash
cp .env.example .env
./scripts/setup.sh
```

Then open:

| Service | URL |
|---------|-----|
| WordPress | <http://localhost:8080> |
| Keycloak | <http://localhost:8180> |
| Portal dashboard | <http://localhost:8080/dashboard/> |
| Informatik dashboard | <http://localhost:8080/dashboard/informatik/> |

All demo passwords are `demo_secret`.

| Login | WordPress role | Fachschaft |
|-------|----------------|-------------|
| `demo-fachschaft` | `fs_informatik_finance` | `informatik` |
| `demo-informatik-reader` | `fs_informatik_reader` | `informatik` |
| `demo-informatik-reader2` | `fs_informatik_reader` | `informatik` |
| `demo-maschinenbau-finance` | `fs_maschinenbau_finance` | `maschinenbau` |
| `demo-maschinenbau-reader` | `fs_maschinenbau_reader` | `maschinenbau` |
| `demo-maschinenbau-reader2` | `fs_maschinenbau_reader` | `maschinenbau` |
| `demo-philosophie-finance` | `fs_philosophie_finance` | `philosophie` |
| `demo-philosophie` | `fs_philosophie_reader` | `philosophie` |
| `demo-philosophie-reader2` | `fs_philosophie_reader` | `philosophie` |
| `demo-asta` | `asta_finance` | all |
| `demo-reviewer` | `asta_reviewer` | all |
| `demo-auditor` | `auditor` | all |
| `demo-unassigned` | `fs_portal_empty` | none |

## Setup Flow

`./scripts/setup.sh` performs the full local setup:

1. Starts MariaDB, WordPress, PostgreSQL, and Keycloak.
2. Waits until WordPress and Keycloak are healthy.
3. Configures the Keycloak realm, roles, OIDC client, groups, and demo users.
4. Installs WordPress and activates the WordPress.org plugin baseline.
5. Imports Fachschaft-scoped Pods post types, roles, pages, and demo data.
6. Verifies plugins, content types, capabilities, portal pages, OIDC settings,
   and demo records.

The setup is designed to be idempotent. Re-running it updates existing
configuration instead of creating duplicate demo records.

## Access Model

WordPress.org-only plugins do not provide reliable Keycloak-claim-based
row-level filtering for one shared custom post type without custom runtime PHP.
The portal therefore uses separate workflow post types per Fachschaft, for
example `b_informatik` and `za_informatik`.

Each Fachschaft role receives capabilities only for its own scoped post types
and Members page permissions only for its own portal pages. AStA and auditor
roles receive cross-Fachschaft capabilities, while the generated global
overview pages are reserved for AStA reviewer/finance and admin roles. Those
overview pages present one merged table per workflow type by combining
setup-generated scoped Pods row shortcodes in the browser; this is not a shared
runtime data model. Workflow records use published WordPress post status so Pods
frontend lists can render them; the workflow lifecycle is stored in the dedicated status
fields. Beschlüsse use `Entwurf`, `Genehmigt`, and `Abgelehnt`;
Zahlungsanweisungen use `Entwurf`, `Eingereicht`, `Rückfrage`, `Stoniert`, and
`Ausgeführt`. Zahlungsanweisungen reference a scoped Beschluss relationship
field; Beschluss detail pages list every Zahlungsanweisung that points back to
that Beschluss. Beschluss and Zahlungsanweisung detail pages show `Betrag
Beschlossen` and calculate `Betrag Offen` from all related payment amounts.
The visible workflow log uses explicit Pods fields such as `decided_at`,
`submitted_at`, `reviewed_at`, and `executed_at`; users set these dates in the
role-gated workflow forms. Detail pages render those fields as one
`Workflow-Log` table at the end of each entry page. Workflow post-meta changes
are still audited in the background by Meta Ledger. The generated
lists use consistent client-side search, status filtering, and pagination to keep the portal
usable as the number of Beschlüsse grows. Direct public record routes are
disabled for the scoped post types, and the frontend pages remain protected by
Members permissions. Item editing stays inside the portal on dedicated
frontend edit pages that load the record ID from the query string, so the
normal workflow never needs wp-admin. Because the project still avoids custom
runtime PHP, exact transition hard-blocking is provided by role-gated generated
forms and verification rather than a custom runtime state-machine hook.

## Documentation

- [Architecture and configuration](docs/architecture.md)
- [Roles and permissions](docs/roles.md)
- [Access management](docs/access-management.md)
- [Frontend workflows](docs/frontend-workflows.md)
- [Workflow process](docs/workflow-process.md)
