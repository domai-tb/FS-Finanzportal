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
  │                                   └── fs-finance-workflow plugin
  │                                         ├── CPT: fachschaft
  │                                         ├── CPT: beschluss
  │                                         └── CPT: zahlungsanweisung
  │
  └─── http://localhost:8180  ──►  Keycloak (SSO / OIDC)
                                       │
                                       └── PostgreSQL (keycloak schema)
```

---

## Services

| Service       | Image                          | Port (host) | Purpose                          |
|---------------|-------------------------------|-------------|----------------------------------|
| `wordpress`   | `wordpress:6-apache`          | 8080        | Main application                 |
| `mariadb`     | `mariadb:11`                  | –           | WordPress database               |
| `keycloak`    | `quay.io/keycloak/keycloak:25`| 8180        | SSO / OpenID Connect provider    |
| `postgres`    | `postgres:16`                 | –           | Keycloak database                |
| `wp-cli`      | `wordpress:cli`               | –           | One-shot setup helper (profile)  |

---

## WordPress Plugin: `fs-finance-workflow`

The custom plugin is intentionally minimal. It registers:

- **Custom Post Types**: `fachschaft`, `beschluss`, `zahlungsanweisung`
- **Custom Post Statuses**: `submitted`, `correction_requested`, `approved`, `archived`

All advanced features (field validation, PDF export, Keycloak role mapping,
email notifications) are left as TODOs for future iterations.

**Prefer existing plugins** over custom code wherever possible:

| Concern                  | Plugin                                    |
|--------------------------|-------------------------------------------|
| SSO / OIDC login         | `daggerhart-openid-connect-generic`       |
| Structured metadata      | `advanced-custom-fields`                  |
| Workflow / status mgmt   | `publishpress`                            |

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
                              Role claim → WP capability mapping
                              (TODO: implement in plugin)
```

---

## Data Flow – Beschluss Lifecycle

```
fachschaft_finance creates Beschluss  [status: draft]
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
  AStA finance exports for accounting
        │
        ▼
  [status: archived]
```

---

## Local Development

See [README.md](../README.md) for setup, reset, and development commands.
