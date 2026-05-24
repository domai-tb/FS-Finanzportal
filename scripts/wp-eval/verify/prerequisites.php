<?php
/**
 * Runtime prerequisite checks for WordPress verification.
 */

function fs_finanzportal_verify_prerequisites()
{
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
}
