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
KC_WORDPRESS_CLIENT_ID="${KC_WORDPRESS_CLIENT_ID:-wordpress}"
WP_URL="${WP_URL:-http://localhost:8080}"
WP="docker compose --profile setup run --rm --entrypoint wp wp-cli --allow-root --path=/var/www/html"

echo "==> Verifying required WordPress plugins..."
REQUIRED_PLUGINS=(
  pods
  daggerhart-openid-connect-generic
  members
  content-control
  publishpress-statuses
  meta-ledger
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

echo "==> Verifying Keycloak theme is present and configured..."
# Check theme directory exists inside the Keycloak container
if docker compose exec -T keycloak test -d /opt/keycloak/themes/asta-finance 2>/dev/null; then
  echo "    Theme folder asta-finance is present in Keycloak container."
else
  echo "ERROR: Theme folder /opt/keycloak/themes/asta-finance not found in Keycloak container." >&2
  exit 1
fi

docker compose exec -T keycloak /opt/keycloak/bin/kcadm.sh config credentials \
  --server http://localhost:8080 \
  --realm master \
  --user "${KC_BOOTSTRAP_ADMIN_USERNAME}" \
  --password "${KC_BOOTSTRAP_ADMIN_PASSWORD}" >/dev/null

if docker compose exec -T keycloak /opt/keycloak/bin/kcadm.sh get "realms/${KC_REALM}" --fields loginTheme | grep -q 'asta-finance'; then
  echo "    Realm loginTheme is set to 'asta-finance'."
else
  echo "ERROR: Realm loginTheme is not set to 'asta-finance'." >&2
  exit 1
fi

echo "==> Verifying Keycloak login page renders..."
curl -fsS \
  "http://localhost:${KC_PORT}/realms/${KC_REALM}/protocol/openid-connect/auth?client_id=${KC_WORDPRESS_CLIENT_ID}&redirect_uri=${WP_URL}/&response_type=code&scope=openid" \
  >/dev/null

echo "==> Setup verification complete."
