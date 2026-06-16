<?php
/**
 * Idempotently configures portal roles, pages, plugin options, and demo data.
 *
 * This entrypoint is executed by WP-CLI during setup only. It intentionally
 * loads setup modules instead of registering runtime WordPress code.
 */

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/naming.php';
require_once __DIR__ . '/lib/roles.php';
require_once __DIR__ . '/lib/wordpress.php';
require_once __DIR__ . '/lib/workflow.php';
require_once __DIR__ . '/lib/render.php';
require_once __DIR__ . '/portal/templates.php';
require_once __DIR__ . '/portal/plugin-settings.php';
require_once __DIR__ . '/portal/pages.php';
require_once __DIR__ . '/portal/demo.php';
require_once __DIR__ . '/portal/ensure.php';
