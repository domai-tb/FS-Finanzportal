<?php
/**
 * Deep verification for the automated WordPress prototype configuration.
 */

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/naming.php';
require_once __DIR__ . '/lib/roles.php';
require_once __DIR__ . '/lib/workflow.php';

function fs_finanzportal_verify_fail(string $message): void
{
    WP_CLI::error($message);
}

function fs_finanzportal_config_path(string $relative_path): string
{
    return fsfp_cli_config_path($relative_path);
}

function fs_finanzportal_load_fachschaften(): array
{
    return fsfp_cli_load_fachschaften();
}

function fs_finanzportal_workflow_types(string $slug): array
{
    return fsfp_cli_workflow_types($slug);
}

function fs_finanzportal_capability_type(string $post_type): string
{
    return fsfp_cli_capability_type($post_type);
}

function fs_finanzportal_global_access_roles(): array
{
    return fsfp_cli_global_access_roles();
}

function fs_finanzportal_global_overview_roles(): array
{
    return fsfp_cli_global_overview_roles();
}

function fs_finanzportal_global_edit_roles(): array
{
    return fs_finanzportal_global_zahlung_edit_roles();
}

function fs_finanzportal_global_beschluss_edit_roles(): array
{
    return fsfp_cli_global_beschluss_edit_roles();
}

function fs_finanzportal_global_zahlung_edit_roles(): array
{
    return fsfp_cli_global_zahlung_edit_roles();
}

function fs_finanzportal_admin_edit_access_cap(): string
{
    return fsfp_cli_admin_edit_access_cap();
}

function fs_finanzportal_fachschaft_access_roles(string $slug): array
{
    return array_merge([
        "fs_{$slug}_reader",
        "fs_{$slug}_finance",
    ], fs_finanzportal_global_access_roles());
}

function fs_finanzportal_fachschaft_view_roles(string $slug): array
{
    return [
        "fs_{$slug}_reader",
        "fs_{$slug}_finance",
        'administrator',
        'portal_admin',
        'auditor',
    ];
}

function fs_finanzportal_read_caps(string $capability_type): array
{
    return fsfp_cli_read_caps($capability_type);
}

function fs_finanzportal_edit_caps(string $capability_type): array
{
    return fsfp_cli_edit_caps($capability_type);
}

function fs_finanzportal_expected_status_values(string $kind): array
{
    return fsfp_cli_workflow_status_values($kind);
}

function fs_finanzportal_pick_values($field): array
{
    $custom = is_array($field) ? (string) ($field['pick_custom'] ?? '') : (isset($field->pick_custom) ? (string) $field->pick_custom : (isset($field['pick_custom']) ? (string) $field['pick_custom'] : ''));
    $values = [];

    foreach (preg_split('/\R/', trim($custom)) ?: [] as $line) {
        if ($line === '') {
            continue;
        }
        $parts = explode('|', $line, 2);
        $values[] = $parts[0];
    }

    return $values;
}

function fs_finanzportal_verify_role_has_caps(string $role_name, array $caps): void
{
    $role = get_role($role_name);
    foreach ($caps as $cap) {
        if (!$role || !$role->has_cap($cap)) {
            fs_finanzportal_verify_fail("Role {$role_name} is missing capability {$cap}.");
        }
    }
}

function fs_finanzportal_verify_role_lacks_caps(string $role_name, array $caps): void
{
    $role = get_role($role_name);
    foreach ($caps as $cap) {
        if ($role && $role->has_cap($cap)) {
            fs_finanzportal_verify_fail("Role {$role_name} must not have capability {$cap}.");
        }
    }
}

function fs_finanzportal_page_by_path(string $path): WP_Post
{
    $page = get_page_by_path($path, OBJECT, 'page');
    if (!$page) {
        fs_finanzportal_verify_fail("Page {$path} is missing.");
    }

    return $page;
}

function fs_finanzportal_verify_page_roles(WP_Post $page, array $expected_roles): void
{
    $actual_roles = get_post_meta($page->ID, '_members_access_role', false);
    sort($actual_roles);
    $expected_roles = array_values(array_unique($expected_roles));
    sort($expected_roles);

    if ($actual_roles !== $expected_roles) {
        fs_finanzportal_verify_fail("Page {$page->post_name} has wrong Members access roles: " . implode(',', $actual_roles));
    }
}

function fs_finanzportal_user_can_view_page(string $login, int $page_id): bool
{
    $user = get_user_by('login', $login);
    if (!$user) {
        fs_finanzportal_verify_fail("User {$login} is missing.");
    }

    wp_set_current_user((int) $user->ID);

    return members_can_current_user_view_post($page_id);
}

function fs_finanzportal_verify_user_can_view(string $login, WP_Post $page): void
{
    if (!fs_finanzportal_user_can_view_page($login, (int) $page->ID)) {
        fs_finanzportal_verify_fail("User {$login} must be able to view {$page->post_name}.");
    }
}

function fs_finanzportal_verify_user_cannot_view(string $login, WP_Post $page): void
{
    if (fs_finanzportal_user_can_view_page($login, (int) $page->ID)) {
        fs_finanzportal_verify_fail("User {$login} must not be able to view {$page->post_name}.");
    }
}

function fs_finanzportal_render_page_as_user(string $login, WP_Post $page): string
{
    $user = get_user_by('login', $login);
    if (!$user) {
        fs_finanzportal_verify_fail("User {$login} is missing.");
    }

    wp_set_current_user((int) $user->ID);

    ob_start();
    $rendered = do_shortcode($page->post_content);
    ob_end_clean();

    return (string) $rendered;
}

if (post_type_exists('beschluss') || post_type_exists('zahlungsanweisung')) {
    fs_finanzportal_verify_fail('Legacy generic workflow post types must not be registered.');
}

if (function_exists('get_mu_plugins')) {
    $mu_plugins = array_keys(get_mu_plugins());
    foreach ($mu_plugins as $mu_plugin) {
        if (str_contains($mu_plugin, 'fs-finanzportal')) {
            fs_finanzportal_verify_fail('Project-specific runtime mu-plugin is still installed.');
        }
    }
}

if (!function_exists('pods_api')) {
    fs_finanzportal_verify_fail('Pods API is unavailable.');
}

if (!function_exists('members_can_current_user_view_post')) {
    fs_finanzportal_verify_fail('Members content permissions API is unavailable.');
}

if (!function_exists('is_plugin_active')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

if (!is_plugin_active('meta-ledger/meta-ledger.php')) {
    fs_finanzportal_verify_fail('Meta Ledger plugin must be active for workflow audit logging.');
}

if (!post_type_exists('fachschaft')) {
    fs_finanzportal_verify_fail('Post type fachschaft is not registered.');
}

$pods_api = pods_api();
$fachschaften = fs_finanzportal_load_fachschaften();
$all_read_caps = [];
$all_edit_caps = [];
$all_beschluss_read_caps = [];
$all_beschluss_write_caps = [];
$all_zahlung_edit_caps = [];
$all_workflow_post_types = [];

foreach ($fachschaften as $fachschaft) {
    $slug = sanitize_key($fachschaft['slug']);
    $types = fs_finanzportal_workflow_types($slug);
    $all_workflow_post_types = array_merge($all_workflow_post_types, array_values($types));

    foreach ($types as $kind => $post_type) {
        if (!post_type_exists($post_type)) {
            fs_finanzportal_verify_fail("Post type {$post_type} is not registered.");
        }

        $capability_type = fs_finanzportal_capability_type($post_type);
        $object = get_post_type_object($post_type);
        $plural = "{$capability_type}s";

        if (!$object || ($object->cap->edit_posts ?? '') !== "edit_{$plural}") {
            fs_finanzportal_verify_fail("Post type {$post_type} does not use custom capability type {$capability_type}.");
        }

        $required_fields = $kind === 'beschluss'
            ? ['fachschaft', 'beschlussdatum', 'betrag', 'zweck_beschreibung', 'beschluss_status', 'decided_at', 'decided_by', 'decision_note', 'belege', 'notes']
            : ['fachschaft', 'zahlungstyp', 'betrag', 'verwendungszweck', 'vorkasse_method', 'vorkasse_begruendung', 'empfaenger_details', 'zahlungs_status', 'submitted_at', 'reviewed_at', 'reviewed_by', 'executed_at', 'executed_by', 'workflow_note', 'belege', 'beschluss_ref', 'notes'];

        if ($kind === 'beschluss') {
            $legacy_field = method_exists($pods_api, 'load_field')
                ? $pods_api->load_field(['pod' => $post_type, 'name' => 'zahlungsanweisung_ref'])
                : null;

            if (!empty($legacy_field)) {
                fs_finanzportal_verify_fail("Pods field zahlungsanweisung_ref must not remain on {$post_type}; related Zahlungsanweisungen are derived from beschluss_ref.");
            }
        }

        foreach ($required_fields as $field_name) {
            $field = method_exists($pods_api, 'load_field')
                ? $pods_api->load_field(['pod' => $post_type, 'name' => $field_name])
                : null;

            if (empty($field)) {
                fs_finanzportal_verify_fail("Pods field {$field_name} is missing on {$post_type}.");
            }

            if (in_array($field_name, ['decided_at', 'submitted_at', 'reviewed_at', 'executed_at'], true)
                && ($field['type'] ?? '') !== 'date'
            ) {
                fs_finanzportal_verify_fail("Pods field {$field_name} on {$post_type} must be a date field.");
            }

            if (in_array($field_name, ['beschlussdatum', 'betrag', 'zweck_beschreibung', 'verwendungszweck'], true)
                && (int) ($field['required'] ?? 0) !== 1
            ) {
                fs_finanzportal_verify_fail("Pods field {$field_name} on {$post_type} must be required.");
            }

            if ($field_name === 'beschluss_status' || $field_name === 'zahlungs_status') {
                $actual_values = fs_finanzportal_pick_values($field);
                $expected_values = fs_finanzportal_expected_status_values($kind);
                sort($actual_values);
                sort($expected_values);
                if ($actual_values !== $expected_values) {
                    fs_finanzportal_verify_fail("Pods field {$field_name} on {$post_type} has wrong workflow statuses: " . implode(',', $actual_values));
                }
            }

            if ($field_name === 'beschluss_ref') {
                if (($field['type'] ?? '') !== 'pick'
                    || ($field['pick_object'] ?? '') !== 'post_type'
                    || ($field['pick_val'] ?? '') !== $types['beschluss']
                    || ($field['pick_where'] ?? '') !== "beschluss_status.meta_value = 'approved'"
                    || (int) ($field['required'] ?? 0) !== 0
                ) {
                    fs_finanzportal_verify_fail("Pods field beschluss_ref on {$post_type} must be an optional relationship to {$types['beschluss']} for Vorkasse support.");
                }
            }

            if ($field_name === 'zahlungstyp') {
                $actual_values = fs_finanzportal_pick_values($field);
                sort($actual_values);
                if (($field['type'] ?? '') !== 'pick'
                    || (int) ($field['required'] ?? 0) !== 1
                    || $actual_values !== ['standard', 'vorkasse']
                ) {
                    fs_finanzportal_verify_fail("Pods field zahlungstyp on {$post_type} must be a required standard/vorkasse pick field.");
                }
            }

            if ($field_name === 'vorkasse_method') {
                $actual_values = fs_finanzportal_pick_values($field);
                sort($actual_values);
                if (($field['type'] ?? '') !== 'pick'
                    || $actual_values !== ['bar', 'ueberweisung']
                ) {
                    fs_finanzportal_verify_fail("Pods field vorkasse_method on {$post_type} must be a bar/ueberweisung pick field.");
                }
            }

            if (in_array($field_name, ['vorkasse_begruendung', 'empfaenger_details'], true)) {
                if (($field['type'] ?? '') !== 'paragraph') {
                    fs_finanzportal_verify_fail("Pods field {$field_name} on {$post_type} must be a paragraph field.");
                }
            }
        }

        $all_read_caps = array_merge($all_read_caps, fs_finanzportal_read_caps($capability_type));
        $all_edit_caps = array_merge($all_edit_caps, fs_finanzportal_edit_caps($capability_type));
    }

    $all_beschluss_read_caps = array_merge($all_beschluss_read_caps, fs_finanzportal_read_caps(fs_finanzportal_capability_type($types['beschluss'])));
    $all_beschluss_write_caps = array_merge(
        $all_beschluss_write_caps,
        array_filter(
            fs_finanzportal_edit_caps(fs_finanzportal_capability_type($types['beschluss'])),
            fn($cap) => str_starts_with($cap, 'edit_') || str_starts_with($cap, 'publish_')
        )
    );
    $all_zahlung_edit_caps = array_merge($all_zahlung_edit_caps, fs_finanzportal_edit_caps(fs_finanzportal_capability_type($types['zahlung'])));
}

foreach (['portal_admin', 'asta_finance', 'asta_reviewer', 'auditor', 'fs_portal_empty'] as $role_name) {
    if (!get_role($role_name)) {
        fs_finanzportal_verify_fail("WordPress role {$role_name} is missing.");
    }
}

$configured_meta_ledger_types = get_option('meta_ledger_post_types', []);
if (!is_array($configured_meta_ledger_types)) {
    fs_finanzportal_verify_fail('Meta Ledger post type configuration must be an array.');
}
sort($configured_meta_ledger_types);
$expected_meta_ledger_types = array_values(array_unique($all_workflow_post_types));
sort($expected_meta_ledger_types);

if ($configured_meta_ledger_types !== $expected_meta_ledger_types) {
    fs_finanzportal_verify_fail('Meta Ledger must be configured for all scoped workflow post types.');
}

if ((int) get_option('meta_ledger_retention_count') < 200) {
    fs_finanzportal_verify_fail('Meta Ledger retention must keep at least 200 entries per meta key.');
}

fs_finanzportal_verify_role_has_caps('portal_admin', ['edit_fachschaft_records', 'publish_fachschaft_records']);
fs_finanzportal_verify_role_has_caps('asta_finance', array_values(array_unique(array_merge($all_beschluss_read_caps, $all_zahlung_edit_caps))));
fs_finanzportal_verify_role_has_caps('asta_reviewer', array_values(array_unique(array_merge($all_beschluss_read_caps, $all_zahlung_edit_caps))));
fs_finanzportal_verify_role_lacks_caps('asta_finance', array_values(array_unique($all_beschluss_write_caps)));
fs_finanzportal_verify_role_lacks_caps('asta_reviewer', array_values(array_unique($all_beschluss_write_caps)));
fs_finanzportal_verify_role_has_caps('auditor', array_values(array_unique($all_read_caps)));
fs_finanzportal_verify_role_lacks_caps('auditor', array_filter(array_values(array_unique($all_edit_caps)), fn($cap) => str_starts_with($cap, 'edit_') || str_starts_with($cap, 'publish_')));
fs_finanzportal_verify_role_lacks_caps('fs_portal_empty', array_filter(array_values(array_unique($all_read_caps)), fn($cap) => $cap !== 'read'));

foreach ($fachschaften as $fachschaft) {
    $slug = sanitize_key($fachschaft['slug']);
    $reader_role = "fs_{$slug}_reader";
    $finance_role = "fs_{$slug}_finance";

    if (!get_role($reader_role) || !get_role($finance_role)) {
        fs_finanzportal_verify_fail("Fachschaft roles for {$slug} are missing.");
    }

    $own_read_caps = [];
    $own_edit_caps = [];
    $other_caps = [];

    foreach ($fachschaften as $candidate) {
        $candidate_slug = sanitize_key($candidate['slug']);
        foreach (fs_finanzportal_workflow_types($candidate_slug) as $post_type) {
            $capability_type = fs_finanzportal_capability_type($post_type);
            if ($candidate_slug === $slug) {
                $own_read_caps = array_merge($own_read_caps, fs_finanzportal_read_caps($capability_type));
                $own_edit_caps = array_merge($own_edit_caps, fs_finanzportal_edit_caps($capability_type));
            } else {
                $other_caps = array_merge($other_caps, fs_finanzportal_read_caps($capability_type), fs_finanzportal_edit_caps($capability_type));
            }
        }
    }

    $own_write_caps = array_filter(array_values(array_unique($own_edit_caps)), fn($cap) => str_starts_with($cap, 'edit_') || str_starts_with($cap, 'publish_'));

    $other_workflow_caps = array_filter(array_values(array_unique($other_caps)), fn($cap) => $cap !== 'read');

    fs_finanzportal_verify_role_has_caps($reader_role, array_values(array_unique($own_read_caps)));
    fs_finanzportal_verify_role_lacks_caps($reader_role, array_values(array_unique(array_merge([fs_finanzportal_admin_edit_access_cap()], $own_write_caps, $other_workflow_caps))));
    fs_finanzportal_verify_role_has_caps($finance_role, array_values(array_unique(array_merge([fs_finanzportal_admin_edit_access_cap()], $own_edit_caps))));
    fs_finanzportal_verify_role_lacks_caps($finance_role, array_values(array_unique($other_workflow_caps)));
}

fs_finanzportal_verify_role_has_caps('administrator', [fs_finanzportal_admin_edit_access_cap()]);
fs_finanzportal_verify_role_has_caps('portal_admin', [fs_finanzportal_admin_edit_access_cap()]);
fs_finanzportal_verify_role_has_caps('asta_finance', [fs_finanzportal_admin_edit_access_cap()]);
fs_finanzportal_verify_role_has_caps('asta_reviewer', [fs_finanzportal_admin_edit_access_cap()]);
fs_finanzportal_verify_role_lacks_caps('auditor', [fs_finanzportal_admin_edit_access_cap()]);
fs_finanzportal_verify_role_lacks_caps('fs_portal_empty', [fs_finanzportal_admin_edit_access_cap()]);

$dashboard = get_page_by_path('dashboard', OBJECT, 'page');
if (!$dashboard || str_contains($dashboard->post_content, '[pods_table')) {
    fs_finanzportal_verify_fail('Dashboard page is missing or still depends on a custom table shortcode.');
}

if (str_contains($dashboard->post_content, 'wp-admin')) {
    fs_finanzportal_verify_fail('Dashboard page must not link to wp-admin.');
}

if (!str_contains($dashboard->post_content, '[members_access role=')
    || !str_contains($dashboard->post_content, 'fs_informatik_reader')
    || !str_contains($dashboard->post_content, 'fs_portal_empty')
) {
    fs_finanzportal_verify_fail('Dashboard must use Members role-gated blocks.');
}

if (!str_contains($dashboard->post_content, 'Alle Beschlüsse öffnen')
    || !str_contains($dashboard->post_content, 'Alle Zahlungsanweisungen öffnen')
) {
    fs_finanzportal_verify_fail('Dashboard must link AStA staff to both unified overview pages.');
}

$menu = wp_get_nav_menu_object('Portal Navigation');
if (!$menu) {
    fs_finanzportal_verify_fail('Portal Navigation menu is missing.');
}

$menu_items = wp_get_nav_menu_items((int) $menu->term_id) ?: [];
$menu_urls = array_map(fn($item) => (string) $item->url, $menu_items);

if (count($menu_items) !== 2) {
    fs_finanzportal_verify_fail('Portal Navigation must only contain Dashboard and Logout.');
}

foreach ($menu_urls as $url) {
    if (str_contains($url, '/dashboard/beschluesse/')
        || str_contains($url, '/dashboard/zahlungsanweisungen/')
        || str_contains($url, '/dashboard/informatik/')
        || str_contains($url, '/dashboard/maschinenbau/')
        || str_contains($url, '/dashboard/philosophie/')
    ) {
        fs_finanzportal_verify_fail('Portal Navigation must not expose Fachschaft or global workflow links.');
    }
}

$block_navigation = get_page_by_path('portal-navigation', OBJECT, 'wp_navigation');
if (!$block_navigation) {
    fs_finanzportal_verify_fail('Portal block navigation is missing.');
}

if (!str_contains($block_navigation->post_content, 'fsfp-nav-beschluesse')
    || !str_contains($block_navigation->post_content, 'fsfp-nav-zahlungsanweisungen')
    || !str_contains($block_navigation->post_content, 'scopedBaseFromPath')
    || !str_contains($block_navigation->post_content, 'dashboardBaseFromContent')
    || !str_contains($block_navigation->post_content, 'pathParts')
) {
    fs_finanzportal_verify_fail('Portal block navigation must expose dynamic workflow header links.');
}

if (str_contains($block_navigation->post_content, '[members_access')
    || str_contains($block_navigation->post_content, '[/members_access]')
    || str_contains($block_navigation->post_content, '/dashboard/informatik/beschluesse/')
) {
    fs_finanzportal_verify_fail('Portal block navigation must not render Members shortcodes or duplicate scoped static links.');
}

wp_set_current_user(0);

$members_settings = get_option('members_settings');
if (!is_array($members_settings) || empty($members_settings['content_permissions'])) {
    fs_finanzportal_verify_fail('Members content permissions must be enabled.');
}

$global_pages = [
    fs_finanzportal_page_by_path('dashboard/beschluesse'),
    fs_finanzportal_page_by_path('dashboard/zahlungsanweisungen'),
];

foreach ($global_pages as $global_page) {
    fs_finanzportal_verify_page_roles($global_page, fs_finanzportal_global_overview_roles());
}

$expected_direct_children = ['beschluesse', 'informatik', 'maschinenbau', 'philosophie', 'zahlungsanweisungen'];
$actual_direct_children = get_posts([
    'post_type' => 'page',
    'post_status' => 'any',
    'post_parent' => $dashboard->ID,
    'posts_per_page' => -1,
]);
$actual_direct_child_slugs = array_map(fn($page) => $page->post_name, $actual_direct_children);
sort($actual_direct_child_slugs);
sort($expected_direct_children);

if ($actual_direct_child_slugs !== $expected_direct_children) {
    fs_finanzportal_verify_fail('Dashboard contains stale or unexpected child pages: ' . implode(',', $actual_direct_child_slugs));
}

$restricted_pages_by_fachschaft = [];

foreach ($fachschaften as $fachschaft) {
    $slug = sanitize_key($fachschaft['slug']);
    $restricted_pages_by_fachschaft[$slug] = [];

    foreach (fs_finanzportal_workflow_types($slug) as $post_type) {
        $post_type_object = get_post_type_object($post_type);
        if (!$post_type_object) {
            fs_finanzportal_verify_fail("Workflow post type {$post_type} is missing.");
        }

        if ($post_type_object->publicly_queryable || $post_type_object->has_archive || $post_type_object->rewrite) {
            fs_finanzportal_verify_fail("Workflow post type {$post_type} must not expose direct public routes.");
        }
    }

    foreach ([
        "dashboard/{$slug}",
        "dashboard/{$slug}/beschluesse",
        "dashboard/{$slug}/beschluss-details",
        "dashboard/{$slug}/beschluss-erstellen",
        "dashboard/{$slug}/beschluss-bearbeiten",
        "dashboard/{$slug}/zahlungsanweisungen",
        "dashboard/{$slug}/zahlungsanweisung-details",
        "dashboard/{$slug}/zahlungsanweisung-erstellen",
        "dashboard/{$slug}/zahlungsanweisung-bearbeiten",
    ] as $path) {
        $portal_page = fs_finanzportal_page_by_path($path);
        $restricted_pages_by_fachschaft[$slug][] = $portal_page;

        $expected_page_roles = fs_finanzportal_fachschaft_access_roles($slug);
        if ($path === "dashboard/{$slug}"
            || str_ends_with($path, '/beschluesse')
            || str_ends_with($path, '/zahlungsanweisungen')
        ) {
            $expected_page_roles = fs_finanzportal_fachschaft_view_roles($slug);
        } elseif (str_contains($path, 'beschluss-erstellen') || str_contains($path, 'beschluss-bearbeiten')) {
            $expected_page_roles = array_merge(["fs_{$slug}_finance"], ['administrator', 'portal_admin']);
        } elseif (str_contains($path, 'zahlungsanweisung-erstellen')) {
            $expected_page_roles = array_merge(["fs_{$slug}_finance"], fs_finanzportal_global_beschluss_edit_roles());
        } elseif (str_contains($path, 'zahlungsanweisung-bearbeiten')) {
            $expected_page_roles = array_merge(["fs_{$slug}_finance"], fs_finanzportal_global_zahlung_edit_roles());
        }

        fs_finanzportal_verify_page_roles($portal_page, $expected_page_roles);

        if (str_contains($portal_page->post_content, '[pods_table')) {
            fs_finanzportal_verify_fail("Frontend portal page {$path} still depends on custom runtime shortcode.");
        }

        if (str_contains($portal_page->post_content, 'orderby=')) {
            fs_finanzportal_verify_fail("Frontend portal page {$path} contains unsafe Pods orderby shortcode SQL.");
        }

        if (str_contains($portal_page->post_content, 'post_status=')) {
            fs_finanzportal_verify_fail("Frontend portal page {$path} must not override post_status in Pods shortcodes.");
        }

        if (str_ends_with($path, '/beschluesse') || str_ends_with($path, '/zahlungsanweisungen')) {
            if (!str_contains($portal_page->post_content, 'fsfp-scoped-overview')
                || !str_contains($portal_page->post_content, 'data-scoped-search')
                || !str_contains($portal_page->post_content, 'data-scoped-status')
                || !str_contains($portal_page->post_content, 'data-scoped-prev')
                || !str_contains($portal_page->post_content, 'data-scoped-next')
                || str_contains($portal_page->post_content, 'search="1"')
                || str_contains($portal_page->post_content, 'filters=')
                || str_contains($portal_page->post_content, 'pagination=')
            ) {
                fs_finanzportal_verify_fail("Frontend list page {$path} must use the shared client-side table controls.");
            }
        }

        if (str_contains($portal_page->post_content, '{@permalink}')) {
            fs_finanzportal_verify_fail("Frontend portal page {$path} must not expose direct workflow permalinks.");
        }

        if (str_contains($path, 'beschluss-erstellen') && str_contains($portal_page->post_content, 'beschluss_status')) {
            fs_finanzportal_verify_fail("Beschluss create page {$path} must not expose the status field.");
        }

        if (str_contains($path, 'zahlungsanweisung-erstellen') && str_contains($portal_page->post_content, 'zahlungs_status')) {
            fs_finanzportal_verify_fail("Zahlungsanweisung create page {$path} must not expose the status field.");
        }
    }
}

foreach ($global_pages as $global_page) {
    if (str_contains($global_page->post_content, 'orderby=')) {
        fs_finanzportal_verify_fail("Global portal page {$global_page->post_name} contains unsafe Pods orderby shortcode SQL.");
    }

    if (str_contains($global_page->post_content, 'post_status=')) {
        fs_finanzportal_verify_fail("Global portal page {$global_page->post_name} must not override post_status in Pods shortcodes.");
    }

    if (str_contains($global_page->post_content, '{@permalink}')) {
        fs_finanzportal_verify_fail("Global portal page {$global_page->post_name} must not expose direct workflow permalinks.");
    }

    if (!str_contains($global_page->post_content, 'fsfp-unified-overview')
        || !str_contains($global_page->post_content, 'data-unified-search')
        || !str_contains($global_page->post_content, 'data-unified-status')
        || !str_contains($global_page->post_content, 'data-unified-fachschaft')
        || !str_contains($global_page->post_content, 'data-unified-prev')
        || !str_contains($global_page->post_content, 'data-unified-next')
        || !str_contains($global_page->post_content, '<tbody data-unified-body>')
    ) {
        fs_finanzportal_verify_fail("Global portal page {$global_page->post_name} must render a unified overview table with filters and pagination.");
    }

    if (str_contains($global_page->post_content, '<h3>Fachschaft Informatik</h3>')
        || str_contains($global_page->post_content, '<h3>Fachschaft Maschinenbau</h3>')
        || str_contains($global_page->post_content, '<h3>Fachschaft Philosophie</h3>')
    ) {
        fs_finanzportal_verify_fail("Global portal page {$global_page->post_name} must not render one visible table section per Fachschaft.");
    }
}

$global_beschluss_rows_template = get_page_by_path('fsfp-global-b_informatik-beschluss-rows', OBJECT, '_pods_template');
if (!$global_beschluss_rows_template
    || !str_contains($global_beschluss_rows_template->post_content, 'return_to=%2Fdashboard%2Fbeschluesse%2F')
) {
    fs_finanzportal_verify_fail('Global Beschluss overview row links must preserve the unified overview as return target.');
}

$global_zahlung_rows_template = get_page_by_path('fsfp-global-za_informatik-zahlung-rows', OBJECT, '_pods_template');
if (!$global_zahlung_rows_template
    || !str_contains($global_zahlung_rows_template->post_content, 'return_to=%2Fdashboard%2Fzahlungsanweisungen%2F')
) {
    fs_finanzportal_verify_fail('Global payment overview row links must preserve the unified overview as return target.');
}

fs_finanzportal_verify_user_can_view('demo-informatik-reader', $restricted_pages_by_fachschaft['informatik'][0]);
fs_finanzportal_verify_user_cannot_view('demo-informatik-reader', $restricted_pages_by_fachschaft['maschinenbau'][0]);
fs_finanzportal_verify_user_cannot_view('demo-informatik-reader', $restricted_pages_by_fachschaft['philosophie'][0]);
foreach ($global_pages as $global_page) {
    fs_finanzportal_verify_user_cannot_view('demo-informatik-reader', $global_page);
}

fs_finanzportal_verify_user_can_view('demo-maschinenbau-reader', $restricted_pages_by_fachschaft['maschinenbau'][0]);
fs_finanzportal_verify_user_cannot_view('demo-maschinenbau-reader', $restricted_pages_by_fachschaft['informatik'][0]);
fs_finanzportal_verify_user_cannot_view('demo-maschinenbau-reader', $restricted_pages_by_fachschaft['philosophie'][0]);
foreach ($global_pages as $global_page) {
    fs_finanzportal_verify_user_cannot_view('demo-maschinenbau-reader', $global_page);
}

foreach (['demo-asta', 'demo-reviewer'] as $global_user) {
    foreach ($global_pages as $global_page) {
        fs_finanzportal_verify_user_can_view($global_user, $global_page);
    }
    foreach ($restricted_pages_by_fachschaft as $pages) {
        fs_finanzportal_verify_user_cannot_view($global_user, $pages[0]);
        fs_finanzportal_verify_user_cannot_view($global_user, $pages[1]);
        fs_finanzportal_verify_user_can_view($global_user, $pages[2]);
        fs_finanzportal_verify_user_cannot_view($global_user, $pages[5]);
        fs_finanzportal_verify_user_can_view($global_user, $pages[6]);
    }
}

foreach ($restricted_pages_by_fachschaft as $pages) {
    fs_finanzportal_verify_user_can_view('demo-auditor', $pages[0]);
}
foreach ($global_pages as $global_page) {
    fs_finanzportal_verify_user_cannot_view('demo-auditor', $global_page);
    fs_finanzportal_verify_user_cannot_view('demo-fachschaft', $global_page);
    fs_finanzportal_verify_user_cannot_view('demo-maschinenbau-finance', $global_page);
    fs_finanzportal_verify_user_cannot_view('demo-philosophie-finance', $global_page);
}

foreach ($global_pages as $global_page) {
    fs_finanzportal_verify_user_cannot_view('demo-unassigned', $global_page);
}
foreach ($restricted_pages_by_fachschaft as $pages) {
    fs_finanzportal_verify_user_cannot_view('demo-unassigned', $pages[0]);
}

wp_set_current_user(0);

$global_beschluesse_content = fs_finanzportal_render_page_as_user('demo-asta', $global_pages[0]);
if (!str_contains($global_beschluesse_content, 'Demo: Technik-Budget Sommerfest')
    || !str_contains($global_beschluesse_content, 'Demo: Erstsemester-Material')
    || !str_contains($global_beschluesse_content, 'Demo: Literatur für Lesekreis')
    || !str_contains($global_beschluesse_content, 'data-fachschaft="informatik"')
    || !str_contains($global_beschluesse_content, 'data-fachschaft="maschinenbau"')
    || !str_contains($global_beschluesse_content, 'data-fachschaft="philosophie"')
) {
    fs_finanzportal_verify_fail('AStA global Beschluss overview must include source rows from multiple Fachschaften.');
}

$global_beschluesse_reviewer_content = fs_finanzportal_render_page_as_user('demo-reviewer', $global_pages[0]);
if (!str_contains($global_beschluesse_reviewer_content, 'Demo: Technik-Budget Sommerfest')
    || !str_contains($global_beschluesse_reviewer_content, 'fsfp-unified-table')
) {
    fs_finanzportal_verify_fail('AStA reviewer must see Beschluss entries in the unified overview table.');
}

$global_zahlungen_raw_content = $global_pages[1]->post_content;
if (!str_contains($global_zahlungen_raw_content, 'fsfp-unified-zahlungen')) {
    fs_finanzportal_verify_fail('AStA global payment overview must include unified review actions for non-executed records.');
}

$global_zahlung_template = get_page_by_path('fsfp-global-za_informatik-zahlung-rows', OBJECT, '_pods_template');
if (!$global_zahlung_template
    || !str_contains($global_zahlung_template->post_content, 'Rückfrage / Ausgeführt')
    || !str_contains($global_zahlung_template->post_content, 'compare="NOT IN"')
) {
    fs_finanzportal_verify_fail('AStA global payment overview row template must include review actions for non-executed records.');
}

$global_zahlungen_reviewer_content = fs_finanzportal_render_page_as_user('demo-reviewer', $global_pages[1]);
if (!str_contains($global_zahlungen_reviewer_content, 'fsfp-unified-table')) {
    fs_finanzportal_verify_fail('AStA reviewer must see the unified Zahlungsanweisungen overview table.');
}

$informatik_beschluesse_page = $restricted_pages_by_fachschaft['informatik'][1];
$beschluss_edit_template = get_page_by_path('fsfp-b_informatik-bearbeiten-all', OBJECT, '_pods_template');
if (!$beschluss_edit_template || !str_contains($beschluss_edit_template->post_content, '[if field="beschluss_status" value="draft"]')) {
    fs_finanzportal_verify_fail('Beschluss edit links must only be shown for draft records.');
}

$informatik_beschluss_edit_page = $restricted_pages_by_fachschaft['informatik'][4];
if (!str_contains($informatik_beschluss_edit_page->post_content, 'decided_at')
    || !str_contains($informatik_beschluss_edit_page->post_content, 'decided_by')
    || !str_contains($informatik_beschluss_edit_page->post_content, 'decision_note')
    || !str_contains($informatik_beschluss_edit_page->post_content, '_pods_location')
    || !str_contains($informatik_beschluss_edit_page->post_content, 'form.dataset.location=absolute')
) {
    fs_finanzportal_verify_fail('Beschluss workflow form must expose decision date, actor, and note fields.');
}

$informatik_beschluss_create_page = $restricted_pages_by_fachschaft['informatik'][3];
if (!str_contains($informatik_beschluss_create_page->post_content, 'fsfp-form-page--beschluss')
    || !str_contains($informatik_beschluss_create_page->post_content, 'fsfp-form-shell')
    || !str_contains($informatik_beschluss_create_page->post_content, 'Beschluss erfassen')
    || !str_contains($informatik_beschluss_create_page->post_content, 'data-form-errors')
    || !str_contains($informatik_beschluss_create_page->post_content, 'Der Betrag muss größer als 0 sein.')
    || !str_contains($informatik_beschluss_create_page->post_content, 'Das Beschlussdatum darf nicht in der Zukunft liegen.')
    || !str_contains($informatik_beschluss_create_page->post_content, 'pods_field_${name}')
    || !str_contains($informatik_beschluss_create_page->post_content, 'fsfpSanityBound')
    || !str_contains($informatik_beschluss_create_page->post_content, '[250,750,1500,3000]')
    || !str_contains($informatik_beschluss_create_page->post_content, 'stopImmediatePropagation')
    || !str_contains($informatik_beschluss_create_page->post_content, 'fsfp-field-invalid')
) {
    fs_finanzportal_verify_fail('Beschluss create page must use the styled form shell and basic sanity checks.');
}

$informatik_reader_content = fs_finanzportal_render_page_as_user('demo-informatik-reader', $informatik_beschluesse_page);
if (!str_contains($informatik_reader_content, 'Demo: Technik-Budget Sommerfest')) {
    fs_finanzportal_verify_fail('Informatik reader must see Informatik Beschluss entries on the frontend list.');
}
if (str_contains($informatik_reader_content, 'wp-admin') || str_contains($informatik_reader_content, 'Erstellen') || str_contains($informatik_reader_content, 'Bearbeiten')) {
    fs_finanzportal_verify_fail('Informatik reader must not see create/edit controls on the frontend list.');
}

$informatik_finance_content = fs_finanzportal_render_page_as_user('demo-fachschaft', $informatik_beschluesse_page);
if (!str_contains($informatik_finance_content, 'Demo: Technik-Budget Sommerfest')) {
    fs_finanzportal_verify_fail('Informatik finance must see Informatik Beschluss entries on the frontend list.');
}
if (str_contains($informatik_finance_content, 'wp-admin')
    || !str_contains($informatik_finance_content, 'return_to=%2Fdashboard%2Finformatik%2Fbeschluesse%2F')
    || !str_contains($informatik_finance_content, 'Neu erstellen')
    || !$beschluss_edit_template
    || !str_contains($beschluss_edit_template->post_content, '/dashboard/informatik/beschluss-bearbeiten/?id=')
    || !str_contains($beschluss_edit_template->post_content, 'Bearbeiten')
) {
    fs_finanzportal_verify_fail('Informatik finance must see frontend create/edit controls on the frontend list.');
}

$informatik_zahlungen_page = $restricted_pages_by_fachschaft['informatik'][5];
$informatik_zahlungen_raw_content = $informatik_zahlungen_page->post_content;
if (!str_contains($informatik_zahlungen_raw_content, 'Zahlungsanweisung vorbereiten')
    || !str_contains($informatik_zahlungen_raw_content, 'AStA-Prüfung')
    || !str_contains($informatik_zahlungen_raw_content, 'fsfp-status-flow')
) {
    fs_finanzportal_verify_fail('Payment list must contain finance and AStA workflow action controls.');
}

$informatik_beschluss_detail_page = $restricted_pages_by_fachschaft['informatik'][2];
if (!str_contains($informatik_beschluss_detail_page->post_content, 'Zugehörige Zahlungsanweisungen')
    || !str_contains($informatik_beschluss_detail_page->post_content, 'name="za_informatik"')
    || !str_contains($informatik_beschluss_detail_page->post_content, 'where="beschluss_ref.ID = {@get.id}"')
    || !str_contains($informatik_beschluss_detail_page->post_content, 'Betrag Beschlossen')
    || !str_contains($informatik_beschluss_detail_page->post_content, 'Betrag Offen')
    || !str_contains($informatik_beschluss_detail_page->post_content, 'data-open-budget')
    || !str_contains($informatik_beschluss_detail_page->post_content, 'Intl.NumberFormat')
    || !str_contains($informatik_beschluss_detail_page->post_content, '[^0-9,.-]')
    || !str_contains($informatik_beschluss_detail_page->post_content, 'var marker=root.querySelector("[data-current-beschluss-id]")')
) {
    fs_finanzportal_verify_fail('Beschluss detail page must list related Zahlungsanweisungen and calculate the open budget.');
}
if (!str_contains($informatik_beschluss_detail_page->post_content, 'Workflow-Log')
    || !str_contains($informatik_beschluss_detail_page->post_content, 'template="fsfp-b_informatik-workflow-log"')
    || !str_contains($informatik_beschluss_detail_page->post_content, 'data-fsfp-back-link')
    || !str_contains($informatik_beschluss_detail_page->post_content, 'params.get("return_to")')
    || !str_contains($informatik_beschluss_detail_page->post_content, 'function pathParts(path){return (path||"").split("/")')
    || str_contains($informatik_beschluss_detail_page->post_content, 'path.replace(/^/+|/+$/g')
    || str_contains($informatik_beschluss_detail_page->post_content, '[pods name="b_informatik" slug="{@get.id}"]<table')
    || str_contains($informatik_beschluss_detail_page->post_content, 'Meta Ledger protokolliert')
) {
    fs_finanzportal_verify_fail('Beschluss detail page must show a unified domain workflow log and contextual back link instead of static audit text.');
}

$beschluss_workflow_template = get_page_by_path('fsfp-b_informatik-workflow-log', OBJECT, '_pods_template');
if (!$beschluss_workflow_template
    || !str_contains($beschluss_workflow_template->post_content, 'fsfp-workflow-log')
    || !str_contains($beschluss_workflow_template->post_content, '<td>Entscheidung</td>')
    || !str_contains($beschluss_workflow_template->post_content, '{@decided_at}')
    || !str_contains($beschluss_workflow_template->post_content, '{@decided_by}')
    || !str_contains($beschluss_workflow_template->post_content, '{@decision_note}')
) {
    fs_finanzportal_verify_fail('Beschluss workflow log template must render decision metadata.');
}

$related_zahlung_template = get_page_by_path('fsfp-za_informatik-related-to-beschluss', OBJECT, '_pods_template');
if (!$related_zahlung_template
    || !str_contains($related_zahlung_template->post_content, '/dashboard/informatik/zahlungsanweisung-details/?id={@ID}')
    || !str_contains($related_zahlung_template->post_content, 'fsfp-related-zahlung-amount')
) {
    fs_finanzportal_verify_fail('Beschluss related Zahlungsanweisungen template must link to payment detail pages and expose amounts for budget calculation.');
}

$informatik_zahlungen_detail_page = $restricted_pages_by_fachschaft['informatik'][6];
if (!str_contains($informatik_zahlungen_detail_page->post_content, 'Workflow-Log')
    || !str_contains($informatik_zahlungen_detail_page->post_content, 'template="fsfp-za_informatik-workflow-log"')
    || !str_contains($informatik_zahlungen_detail_page->post_content, 'data-fsfp-back-link')
    || !str_contains($informatik_zahlungen_detail_page->post_content, 'params.get("return_to")')
    || !str_contains($informatik_zahlungen_detail_page->post_content, 'function pathParts(path){return (path||"").split("/")')
    || str_contains($informatik_zahlungen_detail_page->post_content, 'path.replace(/^/+|/+$/g')
    || str_contains($informatik_zahlungen_detail_page->post_content, '[pods name="za_informatik" slug="{@get.id}"]<table')
    || str_contains($informatik_zahlungen_detail_page->post_content, 'Meta Ledger protokolliert')
) {
    fs_finanzportal_verify_fail('Payment detail page must show a unified domain workflow log and contextual back link instead of static audit text.');
}

$zahlung_workflow_template = get_page_by_path('fsfp-za_informatik-workflow-log', OBJECT, '_pods_template');
if (!$zahlung_workflow_template
    || !str_contains($zahlung_workflow_template->post_content, 'fsfp-workflow-log')
    || !str_contains($zahlung_workflow_template->post_content, '<td>Eingereicht</td>')
    || !str_contains($zahlung_workflow_template->post_content, '<td>Geprüft</td>')
    || !str_contains($zahlung_workflow_template->post_content, '<td>Ausgeführt</td>')
    || !str_contains($zahlung_workflow_template->post_content, '{@submitted_at}')
    || !str_contains($zahlung_workflow_template->post_content, '{@reviewed_at}')
    || !str_contains($zahlung_workflow_template->post_content, '{@executed_at}')
    || !str_contains($zahlung_workflow_template->post_content, '{@workflow_note}')
) {
    fs_finanzportal_verify_fail('Payment workflow log template must render payment workflow metadata.');
}
if (!str_contains($informatik_zahlungen_detail_page->post_content, '/dashboard/informatik/beschluss-details/?id={@beschluss_ref.ID}')
    || !str_contains($informatik_zahlungen_detail_page->post_content, '{@beschluss_ref.post_title}')
    || !str_contains($informatik_zahlungen_detail_page->post_content, '{@beschluss_ref.betrag}')
    || !str_contains($informatik_zahlungen_detail_page->post_content, 'Zahlungsanweisung auf Vorkasse')
    || !str_contains($informatik_zahlungen_detail_page->post_content, '{@vorkasse_method}')
    || !str_contains($informatik_zahlungen_detail_page->post_content, '{@vorkasse_begruendung}')
    || !str_contains($informatik_zahlungen_detail_page->post_content, '{@empfaenger_details}')
    || !str_contains($informatik_zahlungen_detail_page->post_content, 'compare="NOT IN"]<dt>Beschluss')
    || !str_contains($informatik_zahlungen_detail_page->post_content, 'Betrag Zahlungsanweisung')
    || !str_contains($informatik_zahlungen_detail_page->post_content, 'Betrag Beschlossen')
    || !str_contains($informatik_zahlungen_detail_page->post_content, 'Betrag Offen')
    || !str_contains($informatik_zahlungen_detail_page->post_content, 'data-current-beschluss-id="{@beschluss_ref.ID}"')
    || !str_contains($informatik_zahlungen_detail_page->post_content, 'fsfp-budget-source')
) {
    fs_finanzportal_verify_fail('Payment detail page must link the related Beschluss and show calculated budget context.');
}

$zahlung_budget_template = get_page_by_path('fsfp-za_informatik-budget-source', OBJECT, '_pods_template');
if (!$zahlung_budget_template
    || !str_contains($zahlung_budget_template->post_content, 'data-payment-id="{@ID}"')
    || !str_contains($zahlung_budget_template->post_content, 'data-payment-type="{@zahlungstyp}"')
    || !str_contains($zahlung_budget_template->post_content, 'data-beschluss-id="{@beschluss_ref.ID}"')
    || !str_contains($zahlung_budget_template->post_content, 'data-payment-amount="{@betrag}"')
) {
    fs_finanzportal_verify_fail('Payment detail page must have a budget source template for related Zahlungsanweisungen.');
}

$beschluss_budget_template = get_page_by_path('fsfp-b_informatik-budget-source', OBJECT, '_pods_template');
if (!$beschluss_budget_template
    || !str_contains($beschluss_budget_template->post_content, 'fsfp-beschluss-budget-row')
    || !str_contains($beschluss_budget_template->post_content, 'data-beschluss-id="{@ID}"')
    || !str_contains($beschluss_budget_template->post_content, 'data-budget-amount="{@betrag}"')
) {
    fs_finanzportal_verify_fail('Payment forms must have a Beschluss budget source template.');
}

$informatik_zahlung_create_page = $restricted_pages_by_fachschaft['informatik'][7];
if (!str_contains($informatik_zahlung_create_page->post_content, 'fsfp-payment-form-scope')
    || !str_contains($informatik_zahlung_create_page->post_content, 'fsfp-form-page--zahlung')
    || !str_contains($informatik_zahlung_create_page->post_content, 'fsfp-form-shell')
    || !str_contains($informatik_zahlung_create_page->post_content, 'Zahlungsanweisung vorbereiten')
    || !str_contains($informatik_zahlung_create_page->post_content, 'zahlungstyp')
    || !str_contains($informatik_zahlung_create_page->post_content, 'vorkasse_method')
    || !str_contains($informatik_zahlung_create_page->post_content, 'vorkasse_begruendung')
    || !str_contains($informatik_zahlung_create_page->post_content, 'empfaenger_details')
    || !str_contains($informatik_zahlung_create_page->post_content, 'fsfp-payment-budget-guard')
    || !str_contains($informatik_zahlung_create_page->post_content, 'fsfp-beschluss-budget-source')
    || !str_contains($informatik_zahlung_create_page->post_content, 'fsfp-budget-source')
    || !str_contains($informatik_zahlung_create_page->post_content, 'data-form-errors')
    || !str_contains($informatik_zahlung_create_page->post_content, 'Bitte wähle für Standard-Zahlungsanweisungen einen genehmigten Beschluss aus.')
    || !str_contains($informatik_zahlung_create_page->post_content, 'Bitte wähle eine Vorkasse-Methode aus.')
    || !str_contains($informatik_zahlung_create_page->post_content, 'syncVorkasseFields')
    || !str_contains($informatik_zahlung_create_page->post_content, 'beschluss.value=""')
    || !str_contains($informatik_zahlung_create_page->post_content, 'isVorkasse()')
    || !str_contains($informatik_zahlung_create_page->post_content, 'pods_field_${name}')
    || !str_contains($informatik_zahlung_create_page->post_content, 'fsfpSanityBound')
    || !str_contains($informatik_zahlung_create_page->post_content, '[250,750,1500,3000]')
    || !str_contains($informatik_zahlung_create_page->post_content, 'stopImmediatePropagation')
    || !str_contains($informatik_zahlung_create_page->post_content, 'data-budget-warning')
    || !str_contains($informatik_zahlung_create_page->post_content, 'Der Betrag überschreitet das offene Budget')
    || !str_contains($informatik_zahlung_create_page->post_content, 'submit.disabled=true')
    || !str_contains($informatik_zahlung_create_page->post_content, 'row.dataset.paymentType==="vorkasse"')
    || !str_contains($informatik_zahlung_create_page->post_content, 'where="beschluss_status.meta_value = \'approved\'"')
) {
    fs_finanzportal_verify_fail('Payment create page must include the frontend budget guard and approved Beschluss budget source.');
}

$zahlung_edit_template = get_page_by_path('fsfp-za_informatik-bearbeiteneinreichenstornieren-all', OBJECT, '_pods_template');
if (!$zahlung_edit_template || !str_contains($zahlung_edit_template->post_content, 'compare="NOT IN"')) {
    fs_finanzportal_verify_fail('Payment edit links must be hidden for executed records where Pods templates support it.');
}

$informatik_zahlung_edit_page = $restricted_pages_by_fachschaft['informatik'][8];
if (!str_contains($informatik_zahlung_edit_page->post_content, 'submitted_at')
    || !str_contains($informatik_zahlung_edit_page->post_content, 'reviewed_at')
    || !str_contains($informatik_zahlung_edit_page->post_content, 'executed_at')
    || !str_contains($informatik_zahlung_edit_page->post_content, 'workflow_note')
) {
    fs_finanzportal_verify_fail('Payment workflow forms must expose workflow date and note fields.');
}
if (!str_contains($informatik_zahlung_edit_page->post_content, 'fsfp-payment-budget-guard')
    || !str_contains($informatik_zahlung_edit_page->post_content, 'row.dataset.paymentId===currentId')
    || !str_contains($informatik_zahlung_edit_page->post_content, '_pods_location')
    || !str_contains($informatik_zahlung_edit_page->post_content, 'form.dataset.location=absolute')
) {
    fs_finanzportal_verify_fail('Payment edit page must include the frontend budget guard, exclude the current payment from spent budget, and honor contextual return targets.');
}

$informatik_zahlungen_auditor_content = fs_finanzportal_render_page_as_user('demo-auditor', $informatik_zahlungen_page);
if (str_contains($informatik_zahlungen_auditor_content, 'Neu erstellen')
    || str_contains($informatik_zahlungen_auditor_content, 'Bearbeiten')
    || str_contains($informatik_zahlungen_auditor_content, 'Rückfrage / Ausgeführt')
) {
    fs_finanzportal_verify_fail('Auditor must not see payment create/edit/action controls.');
}

$custom_css = function_exists('wp_get_custom_css') ? wp_get_custom_css() : '';
if (!str_contains($custom_css, '.fsfp-form-shell')
    || !str_contains($custom_css, '.fsfp-form-page input[type=text]')
    || !str_contains($custom_css, '.fsfp-form-errors')
    || !str_contains($custom_css, '.fsfp-field-invalid')
) {
    fs_finanzportal_verify_fail('Portal custom CSS must style generated workflow forms and validation states.');
}

wp_set_current_user(0);

if (get_option('rda_access_switch') !== 'capability' || get_option('rda_access_cap') !== fs_finanzportal_admin_edit_access_cap()) {
    fs_finanzportal_verify_fail('Remove Dashboard Access must restrict wp-admin to finance/editor workflow roles.');
}

if ((int) get_option('rda_enable_profile') !== 0) {
    fs_finanzportal_verify_fail('Remove Dashboard Access must block profile access for restricted users.');
}

if (untrailingslashit((string) get_option('rda_redirect_url')) !== untrailingslashit(home_url('/dashboard/'))) {
    fs_finanzportal_verify_fail('Remove Dashboard Access redirect URL must point to /dashboard/.');
}

$hab_settings = get_option('hab_settings');
if (!is_array($hab_settings) || !in_array('fs_portal_empty', $hab_settings['hab_userRoles'] ?? [], true)) {
    fs_finanzportal_verify_fail('Hide Admin Bar settings are missing portal roles.');
}

foreach ($fachschaften as $fachschaft) {
    $slug = sanitize_key($fachschaft['slug']);
    foreach (["fs_{$slug}_reader", "fs_{$slug}_finance"] as $role_name) {
        if (!in_array($role_name, $hab_settings['hab_userRoles'] ?? [], true)) {
            fs_finanzportal_verify_fail("Hide Admin Bar settings are missing {$role_name}.");
        }
    }
}

foreach ($fachschaften as $fachschaft) {
    $slug = sanitize_key($fachschaft['slug']);
    $fachschaft_post = get_page_by_path($slug, OBJECT, 'fachschaft');
    if (!$fachschaft_post) {
        fs_finanzportal_verify_fail("Demo Fachschaft {$slug} is missing.");
    }

    $duplicates = get_posts([
        'post_type' => 'fachschaft',
        'name' => $slug,
        'post_status' => 'any',
        'fields' => 'ids',
        'posts_per_page' => -1,
    ]);

    if (count($duplicates) !== 1) {
        fs_finanzportal_verify_fail("Demo Fachschaft {$slug} is not idempotent; found " . count($duplicates) . ' records.');
    }
}

$demo_vorkasse = get_page_by_path('demo-vorkasse-barkasse-sommerfest', OBJECT, 'za_informatik');
if (!$demo_vorkasse
    || get_post_meta($demo_vorkasse->ID, 'zahlungstyp', true) !== 'vorkasse'
    || get_post_meta($demo_vorkasse->ID, 'vorkasse_method', true) !== 'bar'
    || get_post_meta($demo_vorkasse->ID, 'beschluss_ref', true) !== ''
) {
    fs_finanzportal_verify_fail('Demo Vorkasse payment must be seeded as a Vorkasse record without Beschluss reference.');
}

$demo_vorkasse_transfer = get_page_by_path('demo-vorkasse-ueberweisung-reservierung', OBJECT, 'za_maschinenbau');
if (!$demo_vorkasse_transfer
    || get_post_meta($demo_vorkasse_transfer->ID, 'zahlungstyp', true) !== 'vorkasse'
    || get_post_meta($demo_vorkasse_transfer->ID, 'vorkasse_method', true) !== 'ueberweisung'
    || get_post_meta($demo_vorkasse_transfer->ID, 'empfaenger_details', true) === ''
) {
    fs_finanzportal_verify_fail('Demo bank-transfer Vorkasse payment must include recipient details.');
}

foreach (['demo-fachschaft', 'demo-informatik-reader', 'demo-informatik-reader2', 'demo-maschinenbau-finance', 'demo-maschinenbau-reader', 'demo-maschinenbau-reader2', 'demo-philosophie-finance', 'demo-philosophie', 'demo-philosophie-reader2', 'demo-asta', 'demo-reviewer', 'demo-auditor', 'demo-unassigned'] as $login) {
    if (!get_user_by('login', $login)) {
        fs_finanzportal_verify_fail("Demo WordPress user {$login} is missing.");
    }
}

$oidc = get_option('openid_connect_generic_settings');

if (!is_array($oidc)) {
    fs_finanzportal_verify_fail('OpenID Connect settings option is missing or invalid.');
}

$realm = getenv('KC_REALM') ?: 'fs-finance';
$client_id = getenv('KC_WORDPRESS_CLIENT_ID') ?: 'wordpress';

if (($oidc['client_id'] ?? '') !== $client_id) {
    fs_finanzportal_verify_fail('OpenID Connect client_id does not match KC_WORDPRESS_CLIENT_ID.');
}

if (($oidc['login_type'] ?? '') !== 'auto') {
    fs_finanzportal_verify_fail('OpenID Connect login_type must be auto to force Keycloak login.');
}

if ((int) ($oidc['enforce_privacy'] ?? 0) !== 1) {
    fs_finanzportal_verify_fail('OpenID Connect enforce_privacy must be enabled.');
}

foreach (['endpoint_login', 'endpoint_token', 'endpoint_userinfo', 'endpoint_jwks', 'issuer'] as $key) {
    if (empty($oidc[$key]) || !str_contains($oidc[$key], "/realms/{$realm}")) {
        fs_finanzportal_verify_fail("OpenID Connect {$key} does not point at realm {$realm}.");
    }
}

$demo_file = fs_finanzportal_config_path('demo/beschluesse.json');
$demo_items = json_decode(file_get_contents($demo_file), true);

if (!is_array($demo_items)) {
    fs_finanzportal_verify_fail('Demo Beschluesse JSON is invalid.');
}

foreach ($demo_items as $item) {
    $slug = $item['slug'] ?? '';
    $fachschaft = sanitize_key($item['fachschaft'] ?? '');
    $post_type = fs_finanzportal_workflow_types($fachschaft)['beschluss'];
    $post = get_page_by_path($slug, OBJECT, $post_type);

    if (!$post) {
        fs_finanzportal_verify_fail("Demo Beschluss {$slug} is missing in {$post_type}.");
    }

    if ($post->post_status !== 'publish') {
        fs_finanzportal_verify_fail("Demo Beschluss {$slug} must be published for Pods frontend visibility.");
    }

    $duplicates = get_posts([
        'post_type' => $post_type,
        'name' => $slug,
        'post_status' => 'any',
        'fields' => 'ids',
        'posts_per_page' => -1,
    ]);

    if (count($duplicates) !== 1) {
        fs_finanzportal_verify_fail("Demo Beschluss {$slug} is not idempotent; found " . count($duplicates) . ' records.');
    }
}

foreach ($fachschaften as $fachschaft) {
    $slug = sanitize_key($fachschaft['slug']);
    $types = fs_finanzportal_workflow_types($slug);

    foreach ([
        ['post_type' => $types['beschluss'], 'field' => 'beschluss_status', 'kind' => 'beschluss'],
        ['post_type' => $types['zahlung'], 'field' => 'zahlungs_status', 'kind' => 'zahlung'],
    ] as $status_check) {
        $post_ids = get_posts([
            'post_type' => $status_check['post_type'],
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => -1,
        ]);

        foreach ($post_ids as $post_id) {
            $status = (string) get_post_meta((int) $post_id, $status_check['field'], true);
            if (!in_array($status, fs_finanzportal_expected_status_values($status_check['kind']), true)) {
                fs_finanzportal_verify_fail("Record {$post_id} has invalid {$status_check['field']} value {$status}.");
            }
        }
    }
}

WP_CLI::success('WordPress configuration verified.');
