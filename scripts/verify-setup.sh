#!/usr/bin/env bash
# scripts/verify-setup.sh
#
# Verifies the automated local setup from the host.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# shellcheck source=scripts/verify/env.sh
source "$SCRIPT_DIR/verify/env.sh"
# shellcheck source=scripts/verify/plugins.sh
source "$SCRIPT_DIR/verify/plugins.sh"
# shellcheck source=scripts/verify/wordpress.sh
source "$SCRIPT_DIR/verify/wordpress.sh"
# shellcheck source=scripts/verify/keycloak.sh
source "$SCRIPT_DIR/verify/keycloak.sh"

echo "==> Setup verification complete."
