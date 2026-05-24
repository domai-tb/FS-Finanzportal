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
FACHSCHAFTEN_CONFIG="${PROJECT_ROOT}/wordpress/config/fachschaften.json"

KC=(docker compose exec -T keycloak /opt/keycloak/bin/kcadm.sh)

kc() {
  local attempt
  local output
  local status

  for attempt in 1 2 3 4 5; do
    set +e
    output="$("${KC[@]}" "$@" 2>&1)"
    status=$?
    set -e

    if [ "$status" -eq 0 ]; then
      [ -n "$output" ] && printf '%s\n' "$output"
      return 0
    fi

    if printf '%s\n' "$output" | grep -q 'Failed to get lock'; then
      sleep "$attempt"
      continue
    fi

    printf '%s\n' "$output" >&2
    return "$status"
  done

  printf '%s\n' "$output" >&2
  return "$status"
}

fachschaften_tsv() {
  php -r '
    $file = $argv[1];
    $config = json_decode(file_get_contents($file), true);
    if (!is_array($config) || empty($config["fachschaften"]) || !is_array($config["fachschaften"])) {
      fwrite(STDERR, "Invalid Fachschaften JSON\n");
      exit(1);
    }
    foreach ($config["fachschaften"] as $fachschaft) {
      $slug = preg_replace("/[^a-z0-9_-]/", "", strtolower((string) ($fachschaft["slug"] ?? "")));
      $label = (string) ($fachschaft["label"] ?? $slug);
      $short = (string) ($fachschaft["short_label"] ?? $label);
      if ($slug === "") {
        fwrite(STDERR, "Fachschaft slug must not be empty\n");
        exit(1);
      }
      echo $slug, "\t", $label, "\t", $short, "\n";
    }
  ' "$FACHSCHAFTEN_CONFIG"
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

# Ensure the realm uses the custom login theme if available
echo "==> Setting realm login theme to 'asta-finance' (if available)..."
# Wait for theme folder to be present inside the Keycloak container to avoid timing issues
for i in 1 2 3 4 5; do
  if docker compose exec -T keycloak test -d /opt/keycloak/themes/asta-finance 2>/dev/null; then
    kc update "realms/${KC_REALM}" -s "loginTheme=asta-finance" >/dev/null
    break
  fi
  echo "    Waiting for theme mount to appear inside Keycloak container (attempt $i)..."
  sleep 2
done

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
ensure_realm_role auditor "Internal or external auditor"
ensure_realm_role fs_portal_empty "Authenticated user without Fachschaft workflow access"
while IFS=$'\t' read -r fachschaft_slug fachschaft_label _fachschaft_short; do
  ensure_realm_role "fs_${fachschaft_slug}_finance" "${fachschaft_label} finance officer"
  ensure_realm_role "fs_${fachschaft_slug}_reader" "${fachschaft_label} read-only member"
done < <(fachschaften_tsv)

group_id() {
  local group="$1"

  kc get groups \
    -r "$KC_REALM" \
    -q "search=${group}" \
    --fields id,name \
    --format csv 2>/dev/null \
    | awk -F, -v name="$group" 'NF && $2 != "name" { gsub(/"/, "", $1); gsub(/"/, "", $2); if ($2 == name) { print $1; exit } }'
}

ensure_fachschaft_group() {
  local fachschaft="$1"
  local group="fachschaft-${fachschaft}"

  if [ -z "$(group_id "$group")" ]; then
    kc create groups -r "$KC_REALM" -s "name=${group}" >/dev/null
  fi
}

echo "==> Ensuring Keycloak Fachschaft groups..."
while IFS=$'\t' read -r fachschaft_slug _fachschaft_label _fachschaft_short; do
  ensure_fachschaft_group "$fachschaft_slug"
done < <(fachschaften_tsv)

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

ensure_user_attribute_mapper() {
  local mapper_name="$1"
  local user_attribute="$2"
  local claim_name="$3"
  local mapper_id

  mapper_id="$(
    kc get "clients/${CLIENT_UUID}/protocol-mappers/models" \
      -r "$KC_REALM" \
      --fields id,name \
      --format csv 2>/dev/null \
      | awk -F, -v name="$mapper_name" 'NF && $2 != "name" { gsub(/"/, "", $1); gsub(/"/, "", $2); if ($2 == name) { print $1; exit } }'
  )"

  if [ -n "$mapper_id" ]; then
    return 0
  fi

  kc create "clients/${CLIENT_UUID}/protocol-mappers/models" \
    -r "$KC_REALM" \
    -s "name=${mapper_name}" \
    -s "protocol=openid-connect" \
    -s "protocolMapper=oidc-usermodel-attribute-mapper" \
    -s "config.\"user.attribute\"=${user_attribute}" \
    -s "config.\"claim.name\"=${claim_name}" \
    -s "config.\"jsonType.label\"=String" \
    -s "config.\"multivalued\"=true" \
    -s "config.\"id.token.claim\"=true" \
    -s "config.\"access.token.claim\"=true" \
    -s "config.\"userinfo.token.claim\"=true" >/dev/null
}

ensure_user_attribute_mapper fachschaften fachschaften fachschaften

ensure_group_membership_mapper() {
  local mapper_name="$1"
  local claim_name="$2"
  local mapper_id

  mapper_id="$(
    kc get "clients/${CLIENT_UUID}/protocol-mappers/models" \
      -r "$KC_REALM" \
      --fields id,name \
      --format csv 2>/dev/null \
      | awk -F, -v name="$mapper_name" 'NF && $2 != "name" { gsub(/"/, "", $1); gsub(/"/, "", $2); if ($2 == name) { print $1; exit } }'
  )"

  if [ -n "$mapper_id" ]; then
    return 0
  fi

  kc create "clients/${CLIENT_UUID}/protocol-mappers/models" \
    -r "$KC_REALM" \
    -s "name=${mapper_name}" \
    -s "protocol=openid-connect" \
    -s "protocolMapper=oidc-group-membership-mapper" \
    -s "config.\"claim.name\"=${claim_name}" \
    -s "config.\"full.path\"=false" \
    -s "config.\"id.token.claim\"=true" \
    -s "config.\"access.token.claim\"=true" \
    -s "config.\"userinfo.token.claim\"=true" >/dev/null
}

ensure_group_membership_mapper groups groups

ensure_demo_user() {
  local username="$1"
  local email="$2"
  local first_name="$3"
  local last_name="$4"
  local role="$5"
  local fachschaft="${6:-}"
  local user_id

  echo "    ${username} -> ${role}${fachschaft:+ (${fachschaft})}"

  user_id="$(
    kc get users \
      -r "$KC_REALM" \
      -q "username=${username}" \
      --fields id,username \
      --format csv 2>/dev/null \
      | awk -F, -v username="$username" 'NF && $1 != "id" { gsub(/"/, "", $1); gsub(/"/, "", $2); if ($2 == username) { print $1; exit } }'
  )"

  if [ -z "$user_id" ]; then
    kc create users \
      -r "$KC_REALM" \
      -s "username=${username}" \
      -s enabled=true \
      -s "email=${email}" \
      -s "firstName=${first_name}" \
      -s "lastName=${last_name}" >/dev/null

    user_id="$(
      kc get users \
        -r "$KC_REALM" \
        -q "username=${username}" \
        --fields id,username \
        --format csv 2>/dev/null \
        | awk -F, -v username="$username" 'NF && $1 != "id" { gsub(/"/, "", $1); gsub(/"/, "", $2); if ($2 == username) { print $1; exit } }'
    )"
  else
    kc update "users/${user_id}" \
      -r "$KC_REALM" \
      -s enabled=true \
      -s "email=${email}" \
      -s "firstName=${first_name}" \
      -s "lastName=${last_name}" >/dev/null
  fi

  if [ -z "$user_id" ]; then
    echo "ERROR: Could not resolve Keycloak user ${username}." >&2
    exit 1
  fi

  kc set-password \
    -r "$KC_REALM" \
    --userid "$user_id" \
    --new-password demo_secret \
    --temporary=false >/dev/null

  kc add-roles \
    -r "$KC_REALM" \
    --uusername "$username" \
    --rolename "$role" >/dev/null 2>&1 || true

  if [ -n "$fachschaft" ]; then
    kc update "users/${user_id}" \
      -r "$KC_REALM" \
      -s "attributes.fachschaften=[\"${fachschaft}\"]" >/dev/null

    local fachschaft_group_id
    fachschaft_group_id="$(group_id "fachschaft-${fachschaft}")"
    if [ -n "$fachschaft_group_id" ]; then
      kc update "users/${user_id}/groups/${fachschaft_group_id}" -r "$KC_REALM" -n >/dev/null 2>&1 || true
    fi
  else
    kc update "users/${user_id}" \
      -r "$KC_REALM" \
      -s "attributes.fachschaften=[]" >/dev/null
  fi
}

echo "==> Ensuring Keycloak demo users..."
while IFS=$'\t' read -r fachschaft_slug _fachschaft_label fachschaft_short; do
  finance_user="demo-${fachschaft_slug}-finance"
  reader_user="demo-${fachschaft_slug}-reader"
  reader2_user="demo-${fachschaft_slug}-reader2"

  if [ "$fachschaft_slug" = "informatik" ]; then
    finance_user="demo-fachschaft"
  fi
  if [ "$fachschaft_slug" = "philosophie" ]; then
    reader_user="demo-philosophie"
  fi

  ensure_demo_user "$finance_user" "${finance_user}@example.com" Demo "${fachschaft_short}Finance" "fs_${fachschaft_slug}_finance" "$fachschaft_slug"
  ensure_demo_user "$reader_user" "${reader_user}@example.com" Demo "${fachschaft_short}Reader" "fs_${fachschaft_slug}_reader" "$fachschaft_slug"
  ensure_demo_user "$reader2_user" "${reader2_user}@example.com" Demo "${fachschaft_short}ReaderTwo" "fs_${fachschaft_slug}_reader" "$fachschaft_slug"
done < <(fachschaften_tsv)
ensure_demo_user demo-asta demo-asta@example.com Demo AStA asta_finance
ensure_demo_user demo-reviewer demo-reviewer@example.com Demo Reviewer asta_reviewer
ensure_demo_user demo-auditor demo-auditor@example.com Demo Auditor auditor
ensure_demo_user demo-unassigned demo-unassigned@example.com Demo Unassigned fs_portal_empty

echo "==> Keycloak configuration complete."
echo "    Realm:  ${KC_REALM}"
echo "    Client: ${KC_WORDPRESS_CLIENT_ID}"
echo "    OIDC:   ${KC_EXTERNAL_URL}/realms/${KC_REALM}"
