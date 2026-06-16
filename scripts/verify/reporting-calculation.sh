#!/usr/bin/env bash

set -euo pipefail

echo "==> Verifying reporting calculation regression coverage..."
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
node --test "$SCRIPT_DIR/../tests/reporting-calculation.test.mjs"
