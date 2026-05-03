<?php
/**
 * Deep verification for the automated WordPress prototype configuration.
 */

function fs_finanzportal_verify_fail(string $message): void
{
    WP_CLI::error($message);
}

if (!post_type_exists('beschluss')) {
    fs_finanzportal_verify_fail('Post type beschluss is not registered.');
}

foreach (['fachschaft', 'zahlungsanweisung'] as $post_type) {
    if (!post_type_exists($post_type)) {
        fs_finanzportal_verify_fail("Post type {$post_type} is not registered.");
    }
}

$required_fields = [
    'fachschaft',
    'beschlussdatum',
    'betrag',
    'zweck_beschreibung',
    'beschluss_status',
    'belege',
    'zahlungsanweisung_ref',
];

if (!function_exists('pods_api')) {
    fs_finanzportal_verify_fail('Pods API is unavailable.');
}

$pods_api = pods_api();

foreach ($required_fields as $field_name) {
    $field = null;

    if (method_exists($pods_api, 'load_field')) {
        $field = $pods_api->load_field([
            'pod' => 'beschluss',
            'name' => $field_name,
        ]);
    }

    if (empty($field)) {
        fs_finanzportal_verify_fail("Pods field {$field_name} is missing on beschluss.");
    }
}

foreach (['portal_admin', 'asta_finance', 'asta_reviewer', 'fachschaft_finance', 'fachschaft_reader', 'auditor', 'fsr_member', 'fsr_treasurer', 'fsr_board', 'asta_finance_admin'] as $role_name) {
    if (!get_role($role_name)) {
        fs_finanzportal_verify_fail("WordPress role {$role_name} is missing.");
    }
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

foreach ([
    'fachschaft' => 'fachschaft_record',
    'beschluss' => 'beschluss_record',
    'zahlungsanweisung' => 'zahlungsanweisung_record',
] as $post_type => $capability_type) {
    $object = get_post_type_object($post_type);
    $plural = "{$capability_type}s";

    if (!$object || ($object->cap->edit_posts ?? '') !== "edit_{$plural}") {
        fs_finanzportal_verify_fail("Post type {$post_type} does not use custom capability type {$capability_type}.");
    }
}

fs_finanzportal_verify_role_has_caps('portal_admin', ['edit_fachschaft_records', 'publish_fachschaft_records', 'edit_beschluss_records', 'edit_zahlungsanweisung_records']);
fs_finanzportal_verify_role_has_caps('asta_finance', ['edit_beschluss_records', 'edit_others_beschluss_records', 'publish_beschluss_records', 'edit_zahlungsanweisung_records', 'publish_zahlungsanweisung_records']);
fs_finanzportal_verify_role_has_caps('asta_reviewer', ['edit_beschluss_records', 'edit_others_beschluss_records', 'edit_zahlungsanweisung_records']);
fs_finanzportal_verify_role_has_caps('fachschaft_finance', ['edit_beschluss_records', 'publish_beschluss_records', 'edit_zahlungsanweisung_records', 'publish_zahlungsanweisung_records']);
fs_finanzportal_verify_role_lacks_caps('fachschaft_finance', ['edit_posts', 'publish_posts', 'edit_fachschaft_records', 'publish_fachschaft_records', 'edit_others_beschluss_records', 'edit_others_zahlungsanweisung_records']);
fs_finanzportal_verify_role_lacks_caps('fachschaft_reader', ['edit_posts', 'edit_fachschaft_records', 'edit_beschluss_records', 'edit_zahlungsanweisung_records']);
fs_finanzportal_verify_role_lacks_caps('auditor', ['edit_posts', 'edit_fachschaft_records', 'edit_beschluss_records', 'edit_zahlungsanweisung_records']);
fs_finanzportal_verify_role_has_caps('fsr_treasurer', ['edit_beschluss_records', 'publish_beschluss_records', 'edit_zahlungsanweisung_records', 'publish_zahlungsanweisung_records']);
fs_finanzportal_verify_role_lacks_caps('fsr_member', ['edit_posts', 'edit_fachschaft_records', 'edit_beschluss_records', 'edit_zahlungsanweisung_records']);
fs_finanzportal_verify_role_has_caps('asta_finance_admin', ['edit_others_beschluss_records', 'publish_beschluss_records', 'edit_others_zahlungsanweisung_records']);

$dashboard = get_page_by_path('dashboard', OBJECT, 'page');
if (!$dashboard || str_contains($dashboard->post_content, '[fs_finanzportal_dashboard]')) {
    fs_finanzportal_verify_fail('Dashboard page is missing or still depends on the custom portal shortcode.');
}

if (str_contains($dashboard->post_content, 'wp-admin')) {
    fs_finanzportal_verify_fail('Dashboard page must not link to wp-admin.');
}

foreach ([
    'dashboard/beschluesse',
    'dashboard/beschluss-erstellen',
    'dashboard/zahlungsanweisungen',
    'dashboard/zahlungsanweisung-erstellen',
] as $path) {
    $portal_page = get_page_by_path($path, OBJECT, 'page');
    if (!$portal_page) {
        fs_finanzportal_verify_fail("Frontend portal page {$path} is missing.");
    }

    if (str_contains($portal_page->post_content, '<!-- wp:navigation')) {
        fs_finanzportal_verify_fail("Frontend portal page {$path} must not contain an inline navigation block.");
    }
}

if (str_contains($dashboard->post_content, '<!-- wp:navigation')) {
    fs_finanzportal_verify_fail('Dashboard page must not contain an inline navigation block.');
}

if (get_option('rda_access_switch') !== 'manage_options' || get_option('rda_access_cap') !== 'manage_options') {
    fs_finanzportal_verify_fail('Remove Dashboard Access must restrict wp-admin to manage_options users.');
}

if ((int) get_option('rda_enable_profile') !== 0) {
    fs_finanzportal_verify_fail('Remove Dashboard Access must block profile access for restricted users.');
}

if (untrailingslashit((string) get_option('rda_redirect_url')) !== untrailingslashit(home_url('/dashboard/'))) {
    fs_finanzportal_verify_fail('Remove Dashboard Access redirect URL must point to /dashboard/.');
}

$hab_settings = get_option('hab_settings');
if (!is_array($hab_settings) || !in_array('fachschaft_finance', $hab_settings['hab_userRoles'] ?? [], true) || !in_array('fsr_member', $hab_settings['hab_userRoles'] ?? [], true)) {
    fs_finanzportal_verify_fail('Hide Admin Bar settings are missing normal portal roles.');
}

foreach (['informatik', 'philosophie', 'maschinenbau'] as $slug) {
    $fachschaft = get_page_by_path($slug, OBJECT, 'fachschaft');
    if (!$fachschaft) {
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

foreach (['demo-fachschaft', 'demo-informatik-reader', 'demo-informatik-reader2', 'demo-maschinenbau-finance', 'demo-maschinenbau-reader', 'demo-maschinenbau-reader2', 'demo-philosophie-finance', 'demo-philosophie', 'demo-philosophie-reader2', 'demo-asta', 'demo-reviewer', 'demo-auditor'] as $login) {
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

$admin_columns = get_option('fs_finanzportal_admin_columns_beschluss');

if (!is_array($admin_columns) || count($admin_columns['columns'] ?? []) < 5) {
    fs_finanzportal_verify_fail('Stored Admin Columns Beschluss configuration is missing.');
}

$demo_file = getenv('WP_CONFIG_DIR') . '/demo/beschluesse.json';
$demo_items = json_decode(file_get_contents($demo_file), true);

if (!is_array($demo_items)) {
    fs_finanzportal_verify_fail('Demo Beschluesse JSON is invalid.');
}

foreach ($demo_items as $item) {
    $slug = $item['slug'] ?? '';
    $post = get_page_by_path($slug, OBJECT, 'beschluss');

    if (!$post) {
        fs_finanzportal_verify_fail("Demo Beschluss {$slug} is missing.");
    }

    $duplicates = get_posts([
        'post_type' => 'beschluss',
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
