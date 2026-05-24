#!/usr/bin/env bash

set -euo pipefail

echo "==> Verifying WordPress content model, roles, OIDC, and demo data..."
$WP eval-file /scripts/wp-eval/verify-wordpress-config.php
