# FS-Finanzportal

FS-Finanzportal is a Docker-based WordPress prototype for Fachschaft finance
workflows. It helps Fachschaften capture Beschlüsse, Belege, and later
Zahlungsanweisungen before AStA finance handles the final accounting.

The project is intentionally low-code. WordPress runs with configured plugins,
custom post types, roles, list views, and demo data. There is no custom
WordPress runtime plugin.

## What It Contains

| Area | Implementation |
|------|----------------|
| Application | WordPress 6 with Apache/PHP |
| WordPress database | MariaDB 11 |
| Login / SSO | Keycloak 26.2 with OpenID Connect |
| Keycloak database | PostgreSQL 16 |
| Setup automation | Docker Compose and WP-CLI |
| Content model | Pods package in `wordpress/config/pods/` |
| Admin list view | Admin Columns config in `wordpress/config/admin-columns/` |
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
| Beschluss admin | <http://localhost:8080/wp-admin/edit.php?post_type=beschluss> |
| Dashboard links | <http://localhost:8080/dashboard/> |

Credentials are read from `.env`. The default demo users all use the password
`demo_secret`.

| Login | Role |
|-------|------|
| `demo-fachschaft` | `fachschaft_finance` |
| `demo-philosophie` | `fachschaft_reader` |
| `demo-asta` | `asta_finance` |
| `demo-reviewer` | `asta_reviewer` |
| `demo-auditor` | `auditor` |

## Setup Flow

`./scripts/setup.sh` performs the full local setup:

1. Starts MariaDB, WordPress, PostgreSQL, and Keycloak.
2. Waits until WordPress and Keycloak are healthy.
3. Configures the Keycloak realm, roles, OIDC client, and demo users.
4. Runs WP-CLI to install WordPress, activate plugins, and import configuration.
5. Verifies plugins, content types, roles, OIDC settings, and demo data.

The setup is designed to be idempotent. Re-running it updates existing
configuration instead of creating duplicate demo records.

## Beschluss Workflow

Beschlüsse are managed in the WordPress admin area. The workflow status is a
Pods field named `beschluss_status`.

| Value | Label |
|-------|-------|
| `draft` | Entwurf |
| `submitted` | Eingereicht |
| `correction_requested` | Rückfrage |
| `approved` | Genehmigt |
| `rejected` | Abgelehnt |
| `archived` | Archiviert |

Users change status by editing the record. Strict transition guards and
per-Fachschaft row-level access are not enforced in this no-custom-code
prototype.

## Documentation

- [Architecture and configuration](docs/architecture.md)
- [Roles and permissions](docs/roles.md)
