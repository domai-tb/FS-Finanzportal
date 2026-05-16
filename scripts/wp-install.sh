#!/usr/bin/env bash
# scripts/wp-install.sh
#
# Executed inside the wp-cli container (see compose.yaml).
# Installs WordPress core and activates the plugin baseline. Reproducible
# portal configuration is delegated to configure-wordpress.sh.

set -euo pipefail

WP="wp --allow-root --path=/var/www/html"

wait_for_db() {
  echo "==> Waiting for MariaDB to accept connections..."
  for i in $(seq 1 30); do
    if $WP db check &>/dev/null; then
      echo "    Database is ready."
      return 0
    fi
    echo "    Attempt $i/30 - not ready yet. Sleeping 5 s..."
    sleep 5
  done
  echo "ERROR: Database did not become ready in time." >&2
  exit 1
}

install_plugin() {
  local slug="$1"

  if $WP plugin is-installed "$slug" 2>/dev/null; then
    $WP plugin activate "$slug"
    return 0
  fi

  if ! $WP plugin install "$slug" --activate; then
    echo "    WARN: Could not install ${slug} (check network)." >&2
  fi
}

wait_for_db

if $WP core is-installed 2>/dev/null; then
  echo "==> WordPress already installed - skipping core install."
else
  echo "==> Installing WordPress core..."
  $WP core install \
    --url="${WP_URL}" \
    --title="${WP_TITLE}" \
    --admin_user="${WP_ADMIN_USER}" \
    --admin_password="${WP_ADMIN_PASSWORD}" \
    --admin_email="${WP_ADMIN_EMAIL}" \
    --skip-email
fi

echo "==> Configuring basic WordPress settings..."
$WP option update blogdescription "Fachschafts Finance Workflow Portal"
$WP option update timezone_string "Europe/Berlin"
$WP option update date_format "d.m.Y"
$WP option update time_format "H:i"
$WP option update start_of_week 1
$WP option update default_comment_status closed
$WP rewrite structure '/%postname%/' --hard

if ! $WP config has PODS_SHORTCODE_ALLOW_EVALUATE_TAGS >/dev/null 2>&1; then
  $WP config set PODS_SHORTCODE_ALLOW_EVALUATE_TAGS true --raw --type=constant >/dev/null
fi

echo "==> Installing German language pack..."
if ! $WP language core install de_DE; then
  echo "    WARN: Could not install de_DE language pack (check network)." >&2
fi
if ! $WP site switch-language de_DE; then
  echo "    WARN: Could not switch site language to de_DE." >&2
fi

echo "==> Installing and activating WordPress plugins..."
install_plugin daggerhart-openid-connect-generic
install_plugin pods
install_plugin members
install_plugin content-control
install_plugin publishpress-statuses
install_plugin remove-dashboard-access-for-non-admins
install_plugin hide-admin-bar-based-on-user-roles

if $WP plugin is-installed advanced-access-manager 2>/dev/null; then
  $WP plugin deactivate advanced-access-manager || true
fi

echo "==> Cleaning up default WordPress content..."
$WP post delete 1 --force 2>/dev/null || true
$WP post delete 2 --force 2>/dev/null || true

echo "==> Configuring FS-Finanzportal WordPress prototype..."
sh /scripts/configure-wordpress.sh

echo ""
echo "==> WordPress setup complete."
echo "    URL:   ${WP_URL}"
echo "    Admin: ${WP_ADMIN_USER}"
