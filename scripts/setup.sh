#!/usr/bin/env bash
# scripts/setup.sh
#
# Starts the full FS-Finanzportal stack and runs the WordPress setup.
#
# Usage:
#   cp .env.example .env
#   ./scripts/setup.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_ROOT"

# ── Pre-flight checks ──────────────────────────────────────────────────────────
if ! command -v docker &>/dev/null; then
  echo "ERROR: docker is not installed or not in PATH." >&2
  exit 1
fi

if [ ! -f .env ]; then
  echo "ERROR: .env file not found. Run: cp .env.example .env" >&2
  exit 1
fi

# Load .env so we can echo the ports below
set -o allexport
# shellcheck source=/dev/null
source .env
set +o allexport

# ── Start core services ────────────────────────────────────────────────────────
echo "==> Starting core services (mariadb, wordpress, postgres, keycloak)..."
docker compose up -d mariadb wordpress postgres keycloak

echo "==> Waiting for WordPress to become healthy..."
for i in $(seq 1 30); do
  STATUS=$(docker inspect --format='{{.State.Health.Status}}' \
           "$(docker compose ps -q wordpress)" 2>/dev/null || true)
  if [ "$STATUS" = "healthy" ]; then
    echo "    WordPress is healthy."
    break
  fi
  echo "    Attempt $i/30 – status: ${STATUS:-unknown}. Retrying in 10 s..."
  sleep 10
done

# ── Run WP-CLI setup ──────────────────────────────────────────────────────────
echo "==> Running WordPress installation via WP-CLI..."
docker compose --profile setup run --rm wp-cli

echo ""
echo "============================================================"
echo "  Stack is up!"
echo "  WordPress  →  http://localhost:${WP_PORT:-8080}"
echo "  Keycloak   →  http://localhost:${KC_PORT:-8180}"
echo ""
echo "  Admin credentials are defined in your .env file."
echo "============================================================"
