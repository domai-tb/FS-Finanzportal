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

ensure_role() {
  role="$1"
  label="$2"
  clone="${3:-}"

  if $WP role exists "$role" >/dev/null 2>&1; then
    echo "    Role ${role} already exists."
  elif [ -n "$clone" ]; then
    $WP role create "$role" "$label" --clone="$clone" >/dev/null
  else
    $WP role create "$role" "$label" >/dev/null
  fi
}

add_caps() {
  role="$1"
  shift
  $WP cap add "$role" "$@" >/dev/null
}

echo "==> Ensuring WordPress roles and baseline capabilities..."
ensure_role portal_admin "Portal Admin" administrator
ensure_role asta_finance "AStA Finance"
ensure_role asta_reviewer "AStA Reviewer"
ensure_role fachschaft_finance "Fachschaft Finance"
ensure_role fachschaft_reader "Fachschaft Reader"
ensure_role auditor "Auditor"

add_caps portal_admin read upload_files edit_posts edit_others_posts edit_published_posts publish_posts delete_posts delete_others_posts delete_published_posts manage_options
add_caps asta_finance read upload_files edit_posts edit_others_posts edit_published_posts publish_posts delete_posts delete_others_posts delete_published_posts
add_caps asta_reviewer read edit_posts edit_others_posts edit_published_posts
add_caps fachschaft_finance read upload_files edit_posts edit_published_posts publish_posts delete_posts delete_published_posts
add_caps fachschaft_reader read
add_caps auditor read

echo "==> Importing Admin Columns configuration when supported..."
$WP eval-file /scripts/wp-eval/import-admin-columns.php "${WP_CONFIG_DIR}/admin-columns/beschluss-columns.json"

echo "==> Creating or updating deterministic demo Beschluesse..."
$WP eval '
  $file = getenv("WP_CONFIG_DIR") . "/demo/beschluesse.json";
  $items = json_decode(file_get_contents($file), true);
  if (!is_array($items)) {
    fwrite(STDERR, "Invalid demo Beschluesse JSON\n");
    exit(1);
  }

  foreach ($items as $item) {
    $existing = get_page_by_path($item["slug"], OBJECT, "beschluss");
    $post = [
      "post_type" => "beschluss",
      "post_title" => $item["title"],
      "post_name" => $item["slug"],
      "post_status" => "publish",
    ];

    if ($existing) {
      $post["ID"] = $existing->ID;
      $post_id = wp_update_post($post, true);
    } else {
      $post_id = wp_insert_post($post, true);
    }

    if (is_wp_error($post_id)) {
      fwrite(STDERR, $post_id->get_error_message() . "\n");
      exit(1);
    }

    foreach (["fachschaft", "beschlussdatum", "betrag", "zweck_beschreibung", "zahlungsanweisung_ref"] as $field) {
      update_post_meta($post_id, $field, $item[$field] ?? "");
    }
    update_post_meta($post_id, "beschluss_status", $item["status"] ?? "draft");
  }
'

echo "==> WordPress portal configuration complete."
