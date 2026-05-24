#!/usr/bin/env bash

set -euo pipefail

echo "==> Verifying Keycloak OIDC discovery..."
curl -fsS "http://localhost:${KC_PORT}/realms/${KC_REALM}/.well-known/openid-configuration" >/dev/null

echo "==> Verifying Keycloak theme is present and configured..."
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
