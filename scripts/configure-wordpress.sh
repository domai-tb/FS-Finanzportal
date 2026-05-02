#!/usr/bin/env sh
# scripts/configure-wordpress.sh
#
# Runs inside the wp-cli container. Applies reproducible WordPress plugin
# configuration after WordPress core and required plugins are installed.

set -euo pipefail

WP="wp --allow-root --path=/var/www/html"
WP_CONFIG_DIR="${WP_CONFIG_DIR:-/wordpress/config}"
KC_REALM="${KC_REALM:-fs-finance}"
KC_WORDPRESS_CLIENT_ID="${KC_WORDPRESS_CLIENT_ID:-wordpress}"
KC_WORDPRESS_CLIENT_SECRET="${KC_WORDPRESS_CLIENT_SECRET:?KC_WORDPRESS_CLIENT_SECRET is required}"
KC_EXTERNAL_URL="${KC_EXTERNAL_URL:-http://localhost:8180}"
KC_INTERNAL_URL="${KC_INTERNAL_URL:-http://keycloak:8080}"

echo "==> Configuring OpenID Connect Generic settings..."
OIDC_SETTINGS="$(
  php -r '
    $file = getenv("WP_CONFIG_DIR") . "/oidc/openid-connect-generic.settings.json";
    $settings = json_decode(file_get_contents($file), true);
    if (!is_array($settings)) {
      fwrite(STDERR, "Invalid OIDC settings JSON\n");
      exit(1);
    }
    $realm = getenv("KC_REALM");
    $external = rtrim(getenv("KC_EXTERNAL_URL"), "/");
    $internal = rtrim(getenv("KC_INTERNAL_URL"), "/");
    $settings["client_id"] = getenv("KC_WORDPRESS_CLIENT_ID");
    $settings["client_secret"] = getenv("KC_WORDPRESS_CLIENT_SECRET");
    $settings["endpoint_login"] = $external . "/realms/" . $realm . "/protocol/openid-connect/auth";
    $settings["endpoint_end_session"] = $external . "/realms/" . $realm . "/protocol/openid-connect/logout";
    $settings["issuer"] = $external . "/realms/" . $realm;
    $settings["endpoint_token"] = $internal . "/realms/" . $realm . "/protocol/openid-connect/token";
    $settings["endpoint_userinfo"] = $internal . "/realms/" . $realm . "/protocol/openid-connect/userinfo";
    $settings["endpoint_jwks"] = $internal . "/realms/" . $realm . "/protocol/openid-connect/certs";
    echo json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  '
)"
$WP option update openid_connect_generic_settings "$OIDC_SETTINGS" --format=json >/dev/null

echo "==> Importing Pods Beschluss model..."
if $WP help pods-api >/dev/null 2>&1; then
  $WP pods-api import-pod --file="${WP_CONFIG_DIR}/pods/beschluss-pods-package.json" --replace
else
  $WP eval-file /scripts/wp-eval/import-pods-package.php "${WP_CONFIG_DIR}/pods/beschluss-pods-package.json"
fi
$WP rewrite flush --hard >/dev/null

echo "==> Ensuring portal roles, dashboard, users, Fachschaften, and demo Beschluesse..."
$WP eval-file /scripts/wp-eval/ensure-portal-content.php

echo "==> Importing Admin Columns configuration when supported..."
$WP eval-file /scripts/wp-eval/import-admin-columns.php "${WP_CONFIG_DIR}/admin-columns/beschluss-columns.json"

echo "==> WordPress portal configuration complete."
