#!/usr/bin/env bash
# scripts/wp-install.sh
#
# Executed inside the wp-cli container (see compose.yaml).
# Installs WordPress core, activates plugins, and configures
# the basic settings needed for the FS-Finanzportal prototype.
#
# Environment variables are injected by Docker Compose from the
# wp-cli service definition.

set -euo pipefail

WP="wp --allow-root --path=/var/www/html"

# ── Helper ─────────────────────────────────────────────────────────────────────
wait_for_db() {
  echo "==> Waiting for MariaDB to accept connections..."
  for i in $(seq 1 30); do
    if $WP db check &>/dev/null; then
      echo "    Database is ready."
      return 0
    fi
    echo "    Attempt $i/30 – not ready yet. Sleeping 5 s..."
    sleep 5
  done
  echo "ERROR: Database did not become ready in time." >&2
  exit 1
}

# ── Install WordPress core ─────────────────────────────────────────────────────
wait_for_db

if $WP core is-installed 2>/dev/null; then
  echo "==> WordPress already installed – skipping core install."
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

# ── Basic settings ─────────────────────────────────────────────────────────────
echo "==> Configuring basic WordPress settings..."
$WP option update blogdescription "Fachschafts Finance Workflow Portal"
$WP option update timezone_string "Europe/Berlin"
$WP option update date_format "d.m.Y"
$WP option update time_format "H:i"
$WP option update start_of_week 1      # Monday
$WP option update default_comment_status closed
$WP rewrite structure '/%postname%/' --hard

# ── Language ──────────────────────────────────────────────────────────────────
echo "==> Installing German language pack..."
if ! $WP language core install de_DE; then
  echo "    WARN: Could not install de_DE language pack (check network)."
fi
if ! $WP site switch-language de_DE; then
  echo "    WARN: Could not switch site language to de_DE."
fi

# ── Recommended plugins ───────────────────────────────────────────────────────
# Prefer existing WordPress plugins over custom code (see problem statement).
#
# TODO: Replace placeholder slugs with the actual plugin slugs from
#       wordpress.org once the team agrees on the final selection.

echo "==> Installing recommended plugins..."

# OpenID Connect / Keycloak SSO
# Plugin: daggerhart-openid-connect-generic
$WP plugin install daggerhart-openid-connect-generic --activate || \
  echo "    WARN: Could not install OpenID Connect plugin (check network)."

# Advanced Custom Fields – used for Beschluss / Zahlungsanweisung meta
$WP plugin install advanced-custom-fields --activate || \
  echo "    WARN: Could not install ACF plugin (check network)."

# PublishPress – editorial workflow / status management
$WP plugin install publishpress --activate || \
  echo "    WARN: Could not install PublishPress plugin (check network)."

# ── Custom plugin ─────────────────────────────────────────────────────────────
echo "==> Activating custom fs-finance-workflow plugin..."
$WP plugin activate fs-finance-workflow || \
  echo "    WARN: Could not activate fs-finance-workflow plugin."

# ── Remove default content ────────────────────────────────────────────────────
echo "==> Cleaning up default WordPress content..."
$WP post delete 1 --force 2>/dev/null || true   # Hello World post
$WP post delete 2 --force 2>/dev/null || true   # Sample Page

echo ""
echo "==> WordPress setup complete."
echo "    URL:   ${WP_URL}"
echo "    Admin: ${WP_ADMIN_USER}"
