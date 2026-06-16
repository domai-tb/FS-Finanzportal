<?php
/**
 * Plugin option verification for portal access controls and CSS.
 */

function fs_finanzportal_verify_plugin_settings(array $fachschaften)
{
    $custom_css = function_exists('wp_get_custom_css') ? wp_get_custom_css() : '';
    if (!str_contains($custom_css, '.fsfp-form-shell')
        || !str_contains($custom_css, '.fsfp-form-page input[type=text]')
        || !str_contains($custom_css, '.fsfp-form-errors')
        || !str_contains($custom_css, '.fsfp-form-warnings')
        || !str_contains($custom_css, '.fsfp-queue-panel')
        || !str_contains($custom_css, '.fsfp-status-badge')
        || !str_contains($custom_css, '.fsfp-table-wrap')
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
}
