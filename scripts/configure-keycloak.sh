#!/usr/bin/env bash
# scripts/configure-keycloak.sh
#
# Idempotently configures the Keycloak realm, realm roles, and WordPress OIDC
# client after the Keycloak container is healthy.

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
KC_WORDPRESS_CLIENT_ID="${KC_WORDPRESS_CLIENT_ID:-wordpress}"
KC_WORDPRESS_CLIENT_SECRET="${KC_WORDPRESS_CLIENT_SECRET:?KC_WORDPRESS_CLIENT_SECRET is required}"
WP_URL="${WP_URL:-http://localhost:8080}"
KC_PORT="${KC_PORT:-8180}"
KC_EXTERNAL_URL="http://localhost:${KC_PORT}"

KC=(docker compose exec -T keycloak /opt/keycloak/bin/kcadm.sh)

kc() {
  "${KC[@]}" "$@"
}

echo "==> Authenticating Keycloak admin CLI..."
kc config credentials \
  --server http://localhost:8080 \
  --realm master \
  --user "${KC_BOOTSTRAP_ADMIN_USERNAME}" \
  --password "${KC_BOOTSTRAP_ADMIN_PASSWORD}" >/dev/null

if kc get "realms/${KC_REALM}" >/dev/null 2>&1; then
  echo "==> Keycloak realm ${KC_REALM} already exists."
else
  echo "==> Creating Keycloak realm ${KC_REALM}..."
  kc create realms \
    -s "realm=${KC_REALM}" \
    -s "displayName=FS Finanzportal" \
    -s enabled=true \
    -s sslRequired=external \
    -s registrationAllowed=false \
    -s loginWithEmailAllowed=true \
    -s resetPasswordAllowed=true \
    -s bruteForceProtected=true >/dev/null
fi

ensure_realm_role() {
  local role="$1"
  local description="$2"

  if kc get "roles/${role}" -r "$KC_REALM" >/dev/null 2>&1; then
    kc update "roles/${role}" -r "$KC_REALM" -s "description=${description}" >/dev/null
  else
    kc create roles -r "$KC_REALM" -s "name=${role}" -s "description=${description}" >/dev/null
  fi
}

echo "==> Ensuring Keycloak realm roles..."
ensure_realm_role portal_admin "Full administrative access to the portal"
ensure_realm_role asta_finance "AStA finance team responsible for final accounting"
ensure_realm_role asta_reviewer "AStA reviewer for Beschluesse"
ensure_realm_role fachschaft_finance "Fachschaft finance officer"
ensure_realm_role fachschaft_reader "Fachschaft read-only member"
ensure_realm_role auditor "Internal or external auditor"

client_uuid() {
  kc get clients \
    -r "$KC_REALM" \
    -q "clientId=${KC_WORDPRESS_CLIENT_ID}" \
    --fields id \
    --format csv 2>/dev/null \
    | awk -F, 'NF && $1 != "id" { gsub(/"/, "", $1); print $1; exit }'
}

CLIENT_UUID="$(client_uuid)"

if [ -z "$CLIENT_UUID" ]; then
  echo "==> Creating Keycloak client ${KC_WORDPRESS_CLIENT_ID}..."
  kc create clients \
    -r "$KC_REALM" \
    -s "clientId=${KC_WORDPRESS_CLIENT_ID}" \
    -s "name=FS Finanzportal WordPress" \
    -s enabled=true \
    -s protocol=openid-connect \
    -s publicClient=false \
    -s standardFlowEnabled=true \
    -s implicitFlowEnabled=false \
    -s directAccessGrantsEnabled=false >/dev/null
  CLIENT_UUID="$(client_uuid)"
fi

if [ -z "$CLIENT_UUID" ]; then
  echo "ERROR: Could not resolve Keycloak client UUID for ${KC_WORDPRESS_CLIENT_ID}." >&2
  exit 1
fi

echo "==> Updating Keycloak WordPress OIDC client..."
kc update "clients/${CLIENT_UUID}" \
  -r "$KC_REALM" \
  -s "name=FS Finanzportal WordPress" \
  -s enabled=true \
  -s protocol=openid-connect \
  -s publicClient=false \
  -s standardFlowEnabled=true \
  -s implicitFlowEnabled=false \
  -s directAccessGrantsEnabled=false \
  -s "secret=${KC_WORDPRESS_CLIENT_SECRET}" \
  -s "rootUrl=${WP_URL}" \
  -s "baseUrl=${WP_URL}" \
  -s "adminUrl=${WP_URL}/wp-admin" \
  -s "redirectUris=[\"${WP_URL}/*\",\"${WP_URL}/wp-admin/admin-ajax.php?action=openid-connect-authorize\"]" \
  -s "webOrigins=[\"${WP_URL}\"]" >/dev/null

DEMO_USER_ID="$(
  kc get users \
    -r "$KC_REALM" \
    -q username=demo-fachschaft \
    --fields id \
    --format csv 2>/dev/null \
    | awk -F, 'NF && $1 != "id" { gsub(/"/, "", $1); print $1; exit }'
)"

if [ -n "$DEMO_USER_ID" ]; then
  echo "==> Ensuring demo-fachschaft password is non-temporary..."
  kc set-password \
    -r "$KC_REALM" \
    --userid "$DEMO_USER_ID" \
    --new-password demo_secret \
    --temporary=false >/dev/null
fi

echo "==> Keycloak configuration complete."
echo "    Realm:  ${KC_REALM}"
echo "    Client: ${KC_WORDPRESS_CLIENT_ID}"
echo "    OIDC:   ${KC_EXTERNAL_URL}/realms/${KC_REALM}"
