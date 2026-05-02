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

wait_for_service() {
  local service="$1"
  local label="$2"

  echo "==> Waiting for ${label} to become healthy..."
  for i in $(seq 1 30); do
    STATUS=$(docker inspect --format='{{.State.Health.Status}}' \
             "$(docker compose ps -q "$service")" 2>/dev/null || true)
    if [ "$STATUS" = "healthy" ]; then
      echo "    ${label} is healthy."
      return 0
    fi
    echo "    Attempt $i/30 – status: ${STATUS:-unknown}. Retrying in 10 s..."
    sleep 10
  done

  echo "ERROR: ${label} did not become healthy in time." >&2
  exit 1
}

wait_for_service wordpress WordPress
wait_for_service keycloak Keycloak

# ── Configure Keycloak ────────────────────────────────────────────────────────
echo "==> Configuring Keycloak realm and WordPress OIDC client..."
"$SCRIPT_DIR/configure-keycloak.sh"

# ── Run WP-CLI setup ──────────────────────────────────────────────────────────
echo "==> Running WordPress installation and configuration via WP-CLI..."
docker compose --profile setup run --rm wp-cli

# ── Verify setup ──────────────────────────────────────────────────────────────
echo "==> Verifying automated setup..."
"$SCRIPT_DIR/verify-setup.sh"

echo ""
echo "============================================================"
echo "  Stack is up!"
echo "  WordPress  →  http://localhost:${WP_PORT:-8080}"
echo "  Keycloak   →  http://localhost:${KC_PORT:-8180}"
echo ""
echo "  Admin credentials are defined in your .env file."
echo "============================================================"
