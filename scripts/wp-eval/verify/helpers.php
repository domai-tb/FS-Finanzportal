<?php
/**
 * Shared WordPress configuration verification helpers.
 */

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
