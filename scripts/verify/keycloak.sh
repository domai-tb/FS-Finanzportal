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

CLIENT_UUID="$(docker compose exec -T keycloak /opt/keycloak/bin/kcadm.sh get clients -r "${KC_REALM}" -q "clientId=${KC_WORDPRESS_CLIENT_ID}" --fields id --format csv | awk -F, 'NF && $1 != "id" { gsub(/"/, "", $1); print $1; exit }')"
if [ -z "$CLIENT_UUID" ]; then
  echo "ERROR: Keycloak client ${KC_WORDPRESS_CLIENT_ID} is missing." >&2
  exit 1
fi

MAPPERS="$(docker compose exec -T keycloak /opt/keycloak/bin/kcadm.sh get "clients/${CLIENT_UUID}/protocol-mappers/models" -r "${KC_REALM}" --fields name --format csv)"
for mapper in fachschaften groups; do
  if ! grep -q "$mapper" <<<"$MAPPERS"; then
    echo "ERROR: Keycloak OIDC mapper ${mapper} is missing." >&2
    exit 1
  fi
done

echo "==> Verifying Keycloak login page renders..."
curl -fsS \
  "http://localhost:${KC_PORT}/realms/${KC_REALM}/protocol/openid-connect/auth?client_id=${KC_WORDPRESS_CLIENT_ID}&redirect_uri=${WP_URL}/&response_type=code&scope=openid" \
  >/dev/null

echo "==> Verifying Keycloak roles and groups follow Fachschaften config..."
while IFS=$'\t' read -r fachschaft_slug _fachschaft_label; do
  for role in "fs_${fachschaft_slug}_finance" "fs_${fachschaft_slug}_reader"; do
    docker compose exec -T keycloak /opt/keycloak/bin/kcadm.sh get "roles/${role}" -r "${KC_REALM}" >/dev/null
  done

  if ! docker compose exec -T keycloak /opt/keycloak/bin/kcadm.sh get groups -r "${KC_REALM}" -q "search=fachschaft-${fachschaft_slug}" --fields name --format csv | grep -q "fachschaft-${fachschaft_slug}"; then
    echo "ERROR: Keycloak group fachschaft-${fachschaft_slug} is missing." >&2
    exit 1
  fi
done < <(
  php -r '
    $config = json_decode(file_get_contents("wordpress/config/fachschaften.json"), true);
    if (!is_array($config) || empty($config["fachschaften"])) {
      fwrite(STDERR, "Invalid Fachschaften JSON\n");
      exit(1);
    }
    foreach ($config["fachschaften"] as $fachschaft) {
      echo preg_replace("/[^a-z0-9_-]/", "", strtolower((string) $fachschaft["slug"])), "\t", ($fachschaft["label"] ?? ""), "\n";
    }
  '
)
