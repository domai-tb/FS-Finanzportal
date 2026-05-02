# Architecture – FS-Finanzportal

## Overview

The FS-Finanzportal is a **low-code workflow prototype** that allows German
university Fachschaften (student councils) to manage their internal finance
processes digitally before handing off the final accounting to the AStA
(Allgemeiner Studierendenausschuss).

The system deliberately **does not implement full accounting**. Its purpose is
workflow documentation, approval tracking, and export preparation.

---

## Component Diagram (logical)

```
Browser
  │
  ├─── http://localhost:8080  ──►  WordPress (Apache/PHP)
  │                                   │
  │                                   ├── MariaDB (wp_* tables)
  │                                   └── Plugin configuration (no runtime custom PHP)
  │                                         ├── Pods: content types
  │                                         │     └── Beschluss
  │                                         └── Admin Columns: list views
  │
  └─── http://localhost:8180  ──►  Keycloak (SSO / OIDC)
                                       │
                                       └── PostgreSQL (keycloak schema)
```

---

## Services

| Service       | Image                           | Port (host) | Purpose                          |
|---------------|---------------------------------|-------------|----------------------------------|
| `wordpress`   | `wordpress:6-apache`            | 8080        | Main application                 |
| `mariadb`     | `mariadb:11`                    | –           | WordPress database               |
| `keycloak`    | `quay.io/keycloak/keycloak:26.2`| 8180        | SSO / OpenID Connect provider    |
| `postgres`    | `postgres:16`                   | –           | Keycloak database                |
| `wp-cli`      | `wordpress:cli`                 | –           | One-shot setup helper (profile)  |

---

## Plugin Configuration (no runtime custom PHP)

The WordPress runtime is implemented through plugin configuration. There are no
custom MU-plugins or theme/plugin PHP files loaded by WordPress.

| Concern                  | Plugin                                    |
|--------------------------|-------------------------------------------|
| SSO / OIDC login         | `daggerhart-openid-connect-generic`       |
| Content types & fields   | `pods`                                    |
| List-view columns        | `codepress-admin-columns`                 |
| Roles / capabilities     | `members`                                 |
| Page restriction         | `content-control`                         |
| Workflow status mgmt     | `publishpress-statuses` plus Pods select field `beschluss_status` |

### Pods content types

The first prototype defines these configured custom content types:

| Content type | Purpose |
|--------------|---------|
| Fachschaft | Student council units used by list filters |
| Beschluss | Main workflow record |
| Zahlungsanweisung | Payment instruction record for later workflow expansion |

The `beschluss` type has these configured fields:

| Field | Pods field type | Notes |
|-------|-----------------|-------|
| Fachschaft | Text | Name of the student council unit |
| Betrag | Currency | Requested amount in EUR |
| Beschlussdatum | Date | Date of the Beschluss |
| Zweck | Paragraph Text | Purpose / description |
| Status | Pick (select) | Stored as `beschluss_status` because `status` is reserved by Pods |
| Anhänge | File / Image | Supporting documents (Belege) |
| Zahlungsanweisung reference | Text | Plain reference for v1 |

Runtime custom PHP is intentionally absent. Reproducible setup is handled by
WP-CLI scripts that import plugin configuration and seed demo content.

---

## Authentication Flow

```
User ──► WordPress login page
           │
           └─► "Login with SSO" ──► Keycloak (realm: fs-finance)
                                       │
                              OIDC Authorization Code Flow
                                       │
                              WordPress receives JWT / user info
                                       │
                              WordPress user and role assignment
```

---

## Data Flow – Beschluss Lifecycle

```
fachschaft_finance creates Beschluss in wp-admin  [status: draft]
        │
        ▼
  Submits for review                  [status: submitted]
        │
   ┌────┴─────────────────┐
   ▼                       ▼
asta_reviewer approves  requests correction
[status: approved]      [status: correction_requested]
        │                       │
        │               fachschaft_finance corrects
        │               [status: submitted again]
        ▼
  AStA finance filters/exports in wp-admin
        │
        ▼
  [status: archived]
```

---

## Local Development

See [README.md](../README.md) for setup, reset, and development commands.
