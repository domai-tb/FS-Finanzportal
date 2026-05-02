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
# Edit .env and set strong passwords before going to production!

# 3. Start the stack and install WordPress
./scripts/setup.sh
```

After setup:

| Service   | URL                          | Default credentials (from .env) |
|-----------|------------------------------|----------------------------------|
| WordPress | <http://localhost:8080>      | See `WP_ADMIN_USER` / `WP_ADMIN_PASSWORD` in `.env` |
| Keycloak  | <http://localhost:8180>      | See `KC_BOOTSTRAP_ADMIN_USERNAME` / `KC_BOOTSTRAP_ADMIN_PASSWORD` in `.env` |

---

## Development commands

```bash
# Start all services in the background
docker compose up -d

# View live logs
docker compose logs -f

# Run only WordPress and database (no Keycloak)
docker compose up -d mariadb wordpress

# Run WP-CLI setup manually (e.g. after reset)
docker compose --profile setup run --rm wp-cli

# Open an interactive WP-CLI shell
docker compose run --rm wp-cli wp --allow-root shell

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
│   └── wp-install.sh                   # WP-CLI: install WP + plugins
├── keycloak/
│   └── realms/
│       └── fs-finance-realm.json       # Keycloak realm export (placeholder)
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

