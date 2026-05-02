#!/usr/bin/env bash
# scripts/wp-install.sh
#
# Executed inside the wp-cli container (see compose.yaml).
# Installs WordPress core, activates plugins, and configures
# the basic settings needed for the FS-Finanzportal prototype.
#
# Plugin strategy (low-code first):
#   - Pods               → content types: Beschluss, Zahlungsanweisung
#   - Admin Columns      → list view with Fachschaft, Betrag, Status, Datum
#   - OpenID Connect     → Keycloak SSO login
#   - PublishPress Statuses → workflow status management
#
# No custom PHP plugin is installed in this prototype stage.
# Custom code will be added only when existing plugins cannot cover a need.
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

# ── Plugins ───────────────────────────────────────────────────────────────────
echo "==> Installing plugins..."

# OpenID Connect / Keycloak SSO
# Configure client ID, secret, and endpoints via WP Admin or wp option after install.
# See: Settings → OpenID Connect Client
if ! $WP plugin install daggerhart-openid-connect-generic --activate; then
  echo "    WARN: Could not install OpenID Connect plugin (check network)."
fi

# Pods – content type builder (replaces CPT + ACF for this prototype).
# After install, create content types via Pods Admin:
#   • Beschluss:         fields: Fachschaft (text), Betrag (currency), Datum (date),
#                                Zweck (textarea), Status (select), Anhänge (file)
#   • Zahlungsanweisung: same field set, linked to a Beschluss
# Pods stores all configuration in the database; no custom PHP required.
if ! $WP plugin install pods --activate; then
  echo "    WARN: Could not install Pods plugin (check network)."
fi

# Admin Columns – configures list-view columns in WP Admin without custom PHP.
# After install, go to Settings → Admin Columns and configure columns for
# Beschluss and Zahlungsanweisung: Fachschaft, Betrag, Status, Datum.
if ! $WP plugin install codepress-admin-columns --activate; then
  echo "    WARN: Could not install Admin Columns plugin (check network)."
fi

# PublishPress Statuses – lightweight editorial status management.
# Provides the workflow statuses (submitted, approved, etc.) via UI configuration.
if ! $WP plugin install publishpress-statuses --activate; then
  echo "    WARN: Could not install PublishPress Statuses plugin (check network)."
fi

# ── Remove default content ────────────────────────────────────────────────────
echo "==> Cleaning up default WordPress content..."
$WP post delete 1 --force 2>/dev/null || true   # Hello World post
$WP post delete 2 --force 2>/dev/null || true   # Sample Page

echo ""
echo "==> WordPress setup complete."
echo "    URL:   ${WP_URL}"
echo "    Admin: ${WP_ADMIN_USER}"
echo ""
echo "    Next steps:"
echo "    1. Open ${WP_URL}/wp-admin and log in."
echo "    2. Go to Pods Admin → Add New to create Beschluss and Zahlungsanweisung content types."
echo "    3. Add fields: Fachschaft, Betrag, Datum, Zweck, Status, Anhänge."
echo "    4. Go to Settings → Admin Columns to configure list columns."
echo "    5. Configure OpenID Connect settings for Keycloak SSO."

