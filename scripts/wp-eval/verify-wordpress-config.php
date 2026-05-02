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

foreach (['portal_admin', 'asta_finance', 'asta_reviewer', 'fachschaft_finance', 'fachschaft_reader', 'auditor'] as $role_name) {
    if (!get_role($role_name)) {
        fs_finanzportal_verify_fail("WordPress role {$role_name} is missing.");
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
