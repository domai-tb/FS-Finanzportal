# Architecture and Configuration

FS-Finanzportal is a local, reproducible WordPress workflow prototype. The
runtime behavior comes from WordPress plugins and imported configuration files.
Fachschaft membership is modeled in Keycloak and exposed to WordPress through
OIDC claims.

## System Overview

```text
Browser
  |-- WordPress on http://localhost:8080
  |     |-- MariaDB stores WordPress data
  |     |-- Pods provides content types and fields
  |     |-- Admin Columns configures admin list views
  |     `-- OpenID Connect Generic redirects login to Keycloak
  |
  `-- Keycloak on http://localhost:8180
        `-- PostgreSQL stores realm, users, roles, and clients
```

## Docker Services

| Service | Image | Purpose |
|---------|-------|---------|
| `wordpress` | `wordpress:6-apache` | Main application |
| `mariadb` | `mariadb:11` | WordPress database |
| `wp-cli` | `wordpress:cli` | One-shot WordPress setup container |
| `keycloak` | `quay.io/keycloak/keycloak:26.2` | OIDC identity provider |
| `postgres` | `postgres:16` | Keycloak database |

`wp-cli` only runs when the `setup` profile is used. It mounts the versioned
configuration from `wordpress/config/` read-only and applies it to WordPress.

## Configuration Sources

| File | Purpose |
|------|---------|
| `.env` | Local secrets, URLs, ports, and database credentials |
| `compose.yaml` | Service definitions, volumes, health checks, and mounts |
| `keycloak/realms/fs-finance-realm.json` | Baseline Keycloak realm import |
| `wordpress/config/oidc/openid-connect-generic.settings.json` | OIDC plugin defaults |
| `wordpress/config/pods/beschluss-pods-package.json` | Pods content model |
| `wordpress/config/admin-columns/beschluss-columns.json` | Beschluss list columns |
| `wordpress/config/demo/beschluesse.json` | Seed Beschluss records |

Runtime values such as `KC_WORDPRESS_CLIENT_SECRET`, URLs, and realm names are
read from `.env` and injected during setup.

## Setup Scripts

| Script | Runs where | Responsibility |
|--------|------------|----------------|
| `scripts/setup.sh` | Host | Starts services and orchestrates setup |
| `scripts/configure-keycloak.sh` | Host, via Keycloak CLI | Creates or updates realm, roles, client, and demo users |
| `scripts/wp-install.sh` | `wp-cli` container | Installs WordPress and required plugins |
| `scripts/configure-wordpress.sh` | `wp-cli` container | Imports OIDC, Pods, roles, content, and columns |
| `scripts/verify-setup.sh` | Host | Verifies Keycloak and WordPress setup |

Helper files in `scripts/wp-eval/` run through `wp eval-file`. They keep the
WordPress setup reproducible without becoming runtime code.

## WordPress Plugins

| Plugin | Used for |
|--------|----------|
| `daggerhart-openid-connect-generic` | Keycloak login |
| `pods` | Custom post types and fields |
| `codepress-admin-columns` | Admin list columns |
| `members` | Role and capability management |
| `content-control` | Page access restrictions |
| `publishpress-statuses` | Installed for workflow status support |

The current status values are stored in Pods fields, not as guarded custom
workflow transitions.

## Content Model

Pods defines three private admin-facing post types:

| Post type | Purpose |
|-----------|---------|
| `fachschaft` | Fachschaft master data for filtering and operational grouping |
| `beschluss` | Main decision and approval record |
| `zahlungsanweisung` | Payment instruction model for later workflow expansion |

Important `beschluss` fields:

| Field | Type | Purpose |
|-------|------|---------|
| `fachschaft` | Text | Fachschaft slug/name |
| `beschlussdatum` | Date | Date of the decision |
| `betrag` | Currency | Amount in EUR |
| `zweck_beschreibung` | Paragraph | Purpose and description |
| `beschluss_status` | Select | Workflow status |
| `belege` | File | Attachments |
| `zahlungsanweisung_ref` | Text | Plain payment instruction reference |

Important `zahlungsanweisung` fields:

| Field | Type | Purpose |
|-------|------|---------|
| `fachschaft` | Text | Fachschaft slug/name |
| `betrag` | Currency | Amount in EUR |
| `verwendungszweck` | Paragraph | Payment purpose |
| `zahlungs_status` | Select | Payment workflow status |
| `belege` | File | Attachments |

## Authentication

WordPress uses the OpenID Connect Generic plugin in automatic login mode.
Unauthenticated users are redirected to Keycloak.

The browser-facing endpoints use the external Keycloak URL from `.env`, for
example `http://localhost:8180`. Token, userinfo, and JWKS calls use the
internal Docker URL `http://keycloak:8080`.

The local setup links demo users by username/email and can create missing
WordPress users after successful OIDC login.

## Fachschaft Membership

Each Fachschaft user belongs to exactly one Fachschaft.

Keycloak stores the membership as:

- a group named `fachschaft-<slug>`, for example `fachschaft-informatik`
- a user attribute named `fachschaften`
- an OIDC claim named `fachschaften`
- an OIDC claim named `groups`

The Keycloak setup seeds one `fachschaft_finance` user and multiple
`fachschaft_reader` users per Fachschaft. The WordPress OIDC client receives
the user's realm roles and Fachschaft groups/attributes.

AStA roles, auditors, portal admins, and WordPress administrators are global
roles and are not assigned to a single Fachschaft.

WordPress itself does not enforce per-record Fachschaft isolation in this
no-custom-plugin setup. Enforcing `beschluss.fachschaft` or
`zahlungsanweisung.fachschaft` against the OIDC claims requires a WordPress
access-control plugin that supports claim-based rules, or custom code.

## Admin Workflow

The main workflow happens in WordPress admin:

1. A Fachschaft user creates or edits a `beschluss`.
2. The user sets `beschluss_status` to `submitted`.
3. A reviewer or AStA finance user updates the status to `approved`,
   `rejected`, or `correction_requested`.
4. AStA finance can filter records in the admin list and archive completed
   items.

This is still a prototype boundary: Fachschaft membership is available through
Keycloak claims, but WordPress does not enforce row-level Fachschaft isolation
or strict status transitions.

## Verification

`./scripts/verify-setup.sh` checks:

- Required plugins are active.
- Pods post types and fields exist.
- WordPress roles and demo users exist.
- The dashboard links to the admin workflow.
- OIDC settings point to the configured realm.
- Admin Columns config is stored.
- Demo records are present once.
- Keycloak OIDC discovery is reachable.
