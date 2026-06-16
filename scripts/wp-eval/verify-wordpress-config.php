<?php
/**
 * Deep verification for the automated WordPress prototype configuration.
 */

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/naming.php';
require_once __DIR__ . '/lib/roles.php';
require_once __DIR__ . '/lib/workflow.php';
require_once __DIR__ . '/verify/helpers.php';
require_once __DIR__ . '/verify/prerequisites.php';
require_once __DIR__ . '/verify/content-model.php';
require_once __DIR__ . '/verify/pages.php';
require_once __DIR__ . '/verify/access.php';
require_once __DIR__ . '/verify/frontend.php';
require_once __DIR__ . '/verify/plugin-settings.php';
require_once __DIR__ . '/verify/demo-oidc.php';

fs_finanzportal_verify_prerequisites();
$fachschaften = fs_finanzportal_verify_content_model();
$page_context = fs_finanzportal_verify_pages($fachschaften);

fs_finanzportal_verify_access(
    $page_context['restricted_pages_by_fachschaft'],
    $page_context['global_pages']
);
fs_finanzportal_verify_frontend_workflows(
    $page_context['restricted_pages_by_fachschaft'],
    $page_context['global_pages']
);
fs_finanzportal_verify_plugin_settings($fachschaften);
fs_finanzportal_verify_demo_oidc($fachschaften);

WP_CLI::success('WordPress configuration verified.');
