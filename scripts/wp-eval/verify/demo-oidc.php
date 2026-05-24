<?php
/**
 * Demo data, OIDC, and idempotency verification.
 */

function fs_finanzportal_verify_demo_oidc(array $fachschaften)
{
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
}
