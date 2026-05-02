# FS-Finanzportal

A Docker-Compose-based WordPress finance workflow portal for German university
Fachschaften. Fachschaften can manage **Beschlüsse**, **Belege**, and
**Zahlungsanweisungen** before the AStA handles the actual accounting process.

> **Prototype / low-code stage** – full accounting is intentionally out of scope.

---

## Tech stack

| Component | Technology |
|-----------|-----------|
| Main application | WordPress 6 (Apache/PHP) |
| WordPress database | MariaDB 11 |
| SSO / OpenID Connect | Keycloak 26.2 |
| Keycloak database | PostgreSQL 16 |
| Orchestration | Docker Compose |
| Automated WP setup | WP-CLI |

---

## Quick start

```bash
# 1. Clone the repository
git clone https://github.com/domai-tb/FS-Finanzportal.git
cd FS-Finanzportal

# 2. Copy and adjust the environment file
cp .env.example .env
# Edit .env and set strong passwords before going to production.
# Keep KC_WORDPRESS_CLIENT_SECRET set; it is injected into Keycloak and WordPress OIDC config.
# Keep KC_HOSTNAME as a full URL, e.g. http://localhost:8180, for Keycloak backchannel support.

# 3. Start the stack, install WordPress, and apply reproducible configuration
./scripts/setup.sh
```

After setup:

| Service   | URL                          | Default credentials (from .env) |
|-----------|------------------------------|----------------------------------|
| WordPress | <http://localhost:8080>      | See `WP_ADMIN_USER` / `WP_ADMIN_PASSWORD` in `.env` |
| Keycloak  | <http://localhost:8180>      | See `KC_BOOTSTRAP_ADMIN_USERNAME` / `KC_BOOTSTRAP_ADMIN_PASSWORD` in `.env` |

Main workflow entry points:

| Page | URL |
|------|-----|
| Admin workflow | <http://localhost:8080/wp-admin/edit.php?post_type=beschluss> |
| Dashboard links | <http://localhost:8080/dashboard/> |

Unauthenticated users are redirected to the WordPress/OpenID Connect login flow.
Users work in WordPress admin through configured plugins, custom post types,
roles, fields, and list views. There is no custom runtime WordPress plugin.

### Demo users

The setup creates matching Keycloak and WordPress demo users. All demo passwords
are `demo_secret`.

| Login | Role |
|-------|------|
| `demo-fachschaft` | `fachschaft_finance` |
| `demo-philosophie` | `fachschaft_reader` |
| `demo-asta` | `asta_finance` |
| `demo-reviewer` | `asta_reviewer` |
| `demo-auditor` | `auditor` |

The no-custom-code prototype does not enforce per-Fachschaft row-level access.
Use WordPress roles/capabilities for coarse authorization and the configured
Fachschaft field/list filters for operational separation.

### Beschluss workflow

Beschlüsse use a configured Pods status field for the first finance workflow:

| Status | German label |
|--------|--------------|
| `draft` | Entwurf |
| `submitted` | Eingereicht |
| `correction_requested` | Rückfrage |
| `approved` | Genehmigt |
| `rejected` | Abgelehnt |
| `archived` | Archiviert |

Workflow transitions are managed through WordPress admin field edits and role
capabilities. Strict transition guards, per-record Fachschaft scoping, and a
bespoke frontend dashboard are intentionally not implemented without custom code.

---

## Development commands

```bash
# Start all services in the background
docker compose up -d

# View live logs
docker compose logs -f

# Run only WordPress and database (no Keycloak)
docker compose up -d mariadb wordpress

# Run WP-CLI setup/configuration manually (e.g. after reset)
docker compose --profile setup run --rm wp-cli

# Re-apply Keycloak client/role configuration
./scripts/configure-keycloak.sh

# Verify the automated setup
./scripts/verify-setup.sh

# Open an interactive WP-CLI shell
docker compose run --rm --entrypoint wp wp-cli --allow-root --path=/var/www/html shell

# Connect to MariaDB
docker compose exec mariadb mariadb -u wordpress -pwordpress_secret wordpress

# Connect to PostgreSQL (Keycloak DB)
docker compose exec postgres psql -U keycloak -d keycloak
```

---

## Reset / clean up

```bash
# Stop all containers and remove volumes (⚠ deletes all data)
docker compose down -v

# Then re-run setup from scratch
./scripts/setup.sh
```

---

## Project structure

```
FS-Finanzportal/
├── compose.yaml                        # Docker Compose stack definition
├── .env.example                        # Template for environment variables
├── scripts/
│   ├── setup.sh                        # Start stack + run WP-CLI setup
│   ├── wp-install.sh                   # WP-CLI: install WP + plugins
│   ├── configure-keycloak.sh           # Idempotent Keycloak realm/client setup
│   ├── configure-wordpress.sh          # Idempotent WP plugin/content setup
│   ├── verify-setup.sh                 # Automated setup verification
│   └── wp-eval/                        # WP-CLI setup/verification helpers
├── wordpress/
│   └── config/                         # Versioned plugin/model/demo config
├── keycloak/
│   └── realms/
│       └── fs-finance-realm.json       # Keycloak realm import baseline
└── docs/
    ├── architecture.md                 # System architecture overview
    └── roles.md                        # Role definitions and permissions
```

---

## Documentation

- [Architecture](docs/architecture.md) – component diagram and data flow
- [Roles](docs/roles.md) – role definitions and permission matrix

---

## Contributing

1. Create a feature branch from `main`.
2. Follow the existing code style (simple, readable Bash and Docker Compose).
3. Prefer existing WordPress plugins over custom PHP code.
4. Open a pull request with a clear description of the change.
