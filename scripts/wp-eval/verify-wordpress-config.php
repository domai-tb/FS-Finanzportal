<?php
/**
 * Deep verification for the automated WordPress prototype configuration.
 */

function fs_finanzportal_verify_fail(string $message): void
{
    WP_CLI::error($message);
}

function fs_finanzportal_config_path(string $relative_path): string
{
    return rtrim((string) getenv('WP_CONFIG_DIR'), '/') . '/' . ltrim($relative_path, '/');
}

function fs_finanzportal_load_fachschaften(): array
{
    $config = json_decode(file_get_contents(fs_finanzportal_config_path('fachschaften.json')), true);

    if (!is_array($config) || empty($config['fachschaften']) || !is_array($config['fachschaften'])) {
        fs_finanzportal_verify_fail('Fachschaften config is invalid.');
    }

    return $config['fachschaften'];
}

function fs_finanzportal_workflow_types(string $slug): array
{
    return [
        'beschluss' => "b_{$slug}",
        'zahlung' => "za_{$slug}",
    ];
}

function fs_finanzportal_capability_type(string $post_type): string
{
    return "{$post_type}_record";
}

function fs_finanzportal_global_access_roles(): array
{
    return ['administrator', 'portal_admin', 'asta_finance', 'asta_reviewer', 'auditor'];
}

function fs_finanzportal_global_edit_roles(): array
{
    return ['administrator', 'portal_admin', 'asta_finance', 'asta_reviewer'];
}

function fs_finanzportal_admin_edit_access_cap(): string
{
    return 'fsfp_use_wp_admin';
}

function fs_finanzportal_fachschaft_access_roles(string $slug): array
{
    return array_merge([
        "fs_{$slug}_reader",
        "fs_{$slug}_finance",
    ], fs_finanzportal_global_access_roles());
}

function fs_finanzportal_read_caps(string $capability_type): array
{
    $plural = "{$capability_type}s";

    return [
        'read',
        "read_{$capability_type}",
        "read_private_{$plural}",
    ];
}

function fs_finanzportal_edit_caps(string $capability_type): array
{
    $plural = "{$capability_type}s";

    return array_merge(fs_finanzportal_read_caps($capability_type), [
        "edit_{$capability_type}",
        "edit_{$plural}",
        "edit_others_{$plural}",
        "edit_private_{$plural}",
        "edit_published_{$plural}",
        "publish_{$plural}",
    ]);
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

if (!post_type_exists('fachschaft')) {
    fs_finanzportal_verify_fail('Post type fachschaft is not registered.');
}

$pods_api = pods_api();
$fachschaften = fs_finanzportal_load_fachschaften();
$all_read_caps = [];
$all_edit_caps = [];

foreach ($fachschaften as $fachschaft) {
    $slug = sanitize_key($fachschaft['slug']);
    $types = fs_finanzportal_workflow_types($slug);

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
            ? ['fachschaft', 'beschlussdatum', 'betrag', 'zweck_beschreibung', 'beschluss_status', 'belege', 'zahlungsanweisung_ref', 'notes']
            : ['fachschaft', 'betrag', 'verwendungszweck', 'zahlungs_status', 'belege', 'beschluss_ref', 'notes'];

        foreach ($required_fields as $field_name) {
            $field = method_exists($pods_api, 'load_field')
                ? $pods_api->load_field(['pod' => $post_type, 'name' => $field_name])
                : null;

            if (empty($field)) {
                fs_finanzportal_verify_fail("Pods field {$field_name} is missing on {$post_type}.");
            }
        }

        $all_read_caps = array_merge($all_read_caps, fs_finanzportal_read_caps($capability_type));
        $all_edit_caps = array_merge($all_edit_caps, fs_finanzportal_edit_caps($capability_type));
    }
}

foreach (['portal_admin', 'asta_finance', 'asta_reviewer', 'auditor', 'fs_portal_empty'] as $role_name) {
    if (!get_role($role_name)) {
        fs_finanzportal_verify_fail("WordPress role {$role_name} is missing.");
    }
}

fs_finanzportal_verify_role_has_caps('portal_admin', ['edit_fachschaft_records', 'publish_fachschaft_records']);
fs_finanzportal_verify_role_has_caps('asta_finance', array_values(array_unique($all_edit_caps)));
fs_finanzportal_verify_role_has_caps('asta_reviewer', array_values(array_unique($all_edit_caps)));
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

$members_settings = get_option('members_settings');
if (!is_array($members_settings) || empty($members_settings['content_permissions'])) {
    fs_finanzportal_verify_fail('Members content permissions must be enabled.');
}

$global_pages = [
    fs_finanzportal_page_by_path('dashboard/beschluesse'),
    fs_finanzportal_page_by_path('dashboard/zahlungsanweisungen'),
];

foreach ($global_pages as $global_page) {
    fs_finanzportal_verify_page_roles($global_page, fs_finanzportal_global_access_roles());
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
        "dashboard/{$slug}/beschluss-erstellen",
        "dashboard/{$slug}/beschluss-bearbeiten",
        "dashboard/{$slug}/zahlungsanweisungen",
        "dashboard/{$slug}/zahlungsanweisung-erstellen",
        "dashboard/{$slug}/zahlungsanweisung-bearbeiten",
    ] as $path) {
        $portal_page = fs_finanzportal_page_by_path($path);
        $restricted_pages_by_fachschaft[$slug][] = $portal_page;
        fs_finanzportal_verify_page_roles($portal_page, str_contains($path, 'erstellen') || str_contains($path, 'bearbeiten')
            ? array_merge(["fs_{$slug}_finance"], fs_finanzportal_global_edit_roles())
            : fs_finanzportal_fachschaft_access_roles($slug)
        );

        if (str_contains($portal_page->post_content, '[pods_table')) {
            fs_finanzportal_verify_fail("Frontend portal page {$path} still depends on custom runtime shortcode.");
        }

        if (str_contains($portal_page->post_content, 'orderby=')) {
            fs_finanzportal_verify_fail("Frontend portal page {$path} contains unsafe Pods orderby shortcode SQL.");
        }

        if (str_contains($portal_page->post_content, 'post_status=')) {
            fs_finanzportal_verify_fail("Frontend portal page {$path} must not override post_status in Pods shortcodes.");
        }

        if (str_contains($portal_page->post_content, '{@permalink}')) {
            fs_finanzportal_verify_fail("Frontend portal page {$path} must not expose direct workflow permalinks.");
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

foreach (['demo-asta', 'demo-reviewer', 'demo-auditor'] as $global_user) {
    foreach ($global_pages as $global_page) {
        fs_finanzportal_verify_user_can_view($global_user, $global_page);
    }
    foreach ($restricted_pages_by_fachschaft as $pages) {
        fs_finanzportal_verify_user_can_view($global_user, $pages[0]);
    }
}

foreach ($global_pages as $global_page) {
    fs_finanzportal_verify_user_cannot_view('demo-unassigned', $global_page);
}
foreach ($restricted_pages_by_fachschaft as $pages) {
    fs_finanzportal_verify_user_cannot_view('demo-unassigned', $pages[0]);
}

wp_set_current_user(0);

$informatik_beschluesse_page = $restricted_pages_by_fachschaft['informatik'][1];
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
    || !str_contains($informatik_finance_content, '/dashboard/informatik/beschluss-bearbeiten/?id=')
    || !str_contains($informatik_finance_content, 'Erstellen')
    || !str_contains($informatik_finance_content, 'Bearbeiten')
) {
    fs_finanzportal_verify_fail('Informatik finance must see frontend create/edit controls on the frontend list.');
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

WP_CLI::success('WordPress configuration verified.');
