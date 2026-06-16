#!/usr/bin/env bash

set -euo pipefail

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
