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

# 3. Start the stack, install WordPress, and apply reproducible configuration
./scripts/setup.sh
```

After setup:

| Service   | URL                          | Default credentials (from .env) |
|-----------|------------------------------|----------------------------------|
| WordPress | <http://localhost:8080>      | See `WP_ADMIN_USER` / `WP_ADMIN_PASSWORD` in `.env` |
| Keycloak  | <http://localhost:8180>      | See `KC_BOOTSTRAP_ADMIN_USERNAME` / `KC_BOOTSTRAP_ADMIN_PASSWORD` in `.env` |

Main portal entry point:

| Page | URL |
|------|-----|
| Dashboard | <http://localhost:8080/dashboard/> |

Unauthenticated users are redirected to the WordPress/OpenID Connect login flow.
Portal users are expected to work from `/dashboard`, not from wp-admin.

### Demo users

The setup creates matching Keycloak and WordPress demo users. All demo passwords
are `demo_secret`.

| Login | Role | Fachschaft scope |
|-------|------|------------------|
| `demo-fachschaft` | `fachschaft_finance` | Fachschaft Informatik |
| `demo-philosophie` | `fachschaft_reader` | Fachschaft Philosophie |
| `demo-asta` | `asta_finance` | All Fachschaften |
| `demo-reviewer` | `asta_reviewer` | All Fachschaften |
| `demo-auditor` | `auditor` | All Fachschaften |

For the prototype, Fachschaft ownership is stored in WordPress user meta
(`fsfp_fachschaft`). This is intentionally temporary until a robust Keycloak
group or claim mapping is introduced.

### Beschluss workflow

The dashboard supports the first Fachschaft finance workflow:

| Status | German label |
|--------|--------------|
| `draft` | Entwurf |
| `submitted` | Eingereicht |
| `correction_requested` | Rückfrage |
| `approved` | Genehmigt |
| `rejected` | Abgelehnt |
| `archived` | Archiviert |

Fachschaft finance users can create and edit Beschlüsse for their own
Fachschaft while they are drafts or in Rückfrage, then submit them. AStA finance
users can see all Beschlüsse, edit them, request correction, approve, reject,
and archive approved records. AStA reviewers can request correction on submitted
records. Readers and auditors are read-only.

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
│   └── wp-eval/                        # Temporary WP-CLI PHP helpers
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
