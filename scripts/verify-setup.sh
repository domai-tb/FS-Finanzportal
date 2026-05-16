#!/usr/bin/env bash
# scripts/verify-setup.sh
#
# Verifies the automated local setup from the host.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_ROOT"

if [ -f .env ]; then
  set -o allexport
  # shellcheck source=/dev/null
  source .env
  set +o allexport
fi

KC_REALM="${KC_REALM:-fs-finance}"
KC_PORT="${KC_PORT:-8180}"
WP="docker compose --profile setup run --rm --entrypoint wp wp-cli --allow-root --path=/var/www/html"

echo "==> Verifying required WordPress plugins..."
REQUIRED_PLUGINS=(
  pods
  daggerhart-openid-connect-generic
  members
  content-control
  publishpress-statuses
  remove-dashboard-access-for-non-admins
  hide-admin-bar-based-on-user-roles
)

ACTIVE_PLUGINS="$($WP plugin list --status=active --field=name)"

for plugin in "${REQUIRED_PLUGINS[@]}"; do
  if ! grep -Fxq "$plugin" <<<"$ACTIVE_PLUGINS"; then
    echo "ERROR: Required plugin '$plugin' is not active." >&2
    exit 1
  fi
done

echo "==> Verifying WordPress content model, roles, OIDC, and demo data..."
$WP eval-file /scripts/wp-eval/verify-wordpress-config.php

echo "==> Verifying Keycloak OIDC discovery..."
curl -fsS "http://localhost:${KC_PORT}/realms/${KC_REALM}/.well-known/openid-configuration" >/dev/null

echo "==> Setup verification complete."
