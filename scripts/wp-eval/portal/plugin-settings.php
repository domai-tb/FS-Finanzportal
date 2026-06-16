<?php
/**
 * Plugin option setup for the generated portal.
 */

function fsfp_cli_configure_members_settings(): void
{
    $members_settings = get_option('members_settings', []);
    if (!is_array($members_settings)) {
        $members_settings = [];
    }
    $members_settings['content_permissions'] = 1;
    $members_settings['hide_posts_rest_api'] = 1;
    $members_settings['content_permissions_error'] = 'Sie haben keinen Zugriff auf diese Fachschaftsseite. Prüfen Sie zuerst, ob Sie mit dem richtigen Fachschafts-Konto angemeldet sind und im Dashboard die passende Fachschaft geöffnet haben. Falls der Zugriff trotzdem fehlen sollte, melden Sie sich neu an oder wenden Sie sich an Ihre Fachschafts-Finanzrolle beziehungsweise die Portal-Administration.';
    update_option('members_settings', $members_settings);
}

function fsfp_cli_configure_portal_access_plugins(array $fachschaften): void
{
    update_option('rda_access_switch', 'capability');
    update_option('rda_access_cap', fsfp_cli_admin_edit_access_cap());
    update_option('rda_enable_profile', 0);
    update_option('rda_redirect_url', home_url('/dashboard/'));
    update_option('rda_login_message', '');
    
    $hidden_admin_bar_roles = fsfp_cli_roles_config()['hidden_admin_bar_base_roles'];
    foreach ($fachschaften as $fachschaft) {
        $slug = sanitize_key($fachschaft['slug']);
        $hidden_admin_bar_roles[] = "fs_{$slug}_reader";
        $hidden_admin_bar_roles[] = "fs_{$slug}_finance";
    }
    
    update_option('hab_settings', [
        'hab_disableforall' => 'no',
        'hab_userRoles' => array_values(array_unique($hidden_admin_bar_roles)),
        'hab_capabilities' => '',
        'hab_disableforallGuests' => 'no',
    ]);
    
    delete_option('fs_finanzportal_aam_policy_manifest');
}

function fsfp_cli_apply_portal_custom_css(): void
{
    if (!function_exists('wp_update_custom_css_post')) {
        return;
    }

    wp_update_custom_css_post(fsfp_cli_read_config_file('portal/assets/portal.css'));
}
