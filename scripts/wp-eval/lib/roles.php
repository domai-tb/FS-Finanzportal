<?php
/**
 * Setup-time helpers for FS-Finanzportal.
 */

function fsfp_cli_role(string $role, string $label, string $clone = ''): void
{
    if (!get_role($role)) {
        $caps = [];
        if ($clone !== '') {
            $clone_role = get_role($clone);
            $caps = $clone_role ? $clone_role->capabilities : [];
        }
        add_role($role, $label, $caps);
    }
}

function fsfp_cli_add_caps(string $role, array $caps): void
{
    $role_obj = get_role($role);
    if (!$role_obj) {
        WP_CLI::error("Role {$role} is missing.");
    }

    foreach ($caps as $cap) {
        $role_obj->add_cap($cap);
    }
}

function fsfp_cli_sync_caps(string $role, array $caps): void
{
    $role_obj = get_role($role);
    if (!$role_obj) {
        WP_CLI::error("Role {$role} is missing.");
    }

    foreach (array_keys($role_obj->capabilities) as $cap) {
        $role_obj->remove_cap($cap);
    }

    fsfp_cli_add_caps($role, array_values(array_unique($caps)));
}

function fsfp_cli_post_type_caps(string $capability_type): array
{
    $plural = "{$capability_type}s";

    return [
        "read_{$capability_type}",
        "edit_{$capability_type}",
        "delete_{$capability_type}",
        "read_private_{$plural}",
        "edit_{$plural}",
        "edit_others_{$plural}",
        "edit_private_{$plural}",
        "edit_published_{$plural}",
        "publish_{$plural}",
        "delete_{$plural}",
        "delete_private_{$plural}",
        "delete_published_{$plural}",
        "delete_others_{$plural}",
    ];
}

function fsfp_cli_read_caps(string $capability_type): array
{
    $plural = "{$capability_type}s";

    return [
        'read',
        "read_{$capability_type}",
        "read_private_{$plural}",
    ];
}

function fsfp_cli_edit_caps(string $capability_type): array
{
    $plural = "{$capability_type}s";

    return array_merge(fsfp_cli_read_caps($capability_type), [
        "edit_{$capability_type}",
        "edit_{$plural}",
        "edit_others_{$plural}",
        "edit_private_{$plural}",
        "edit_published_{$plural}",
        "publish_{$plural}",
    ]);
}
