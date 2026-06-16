<?php
/**
 * Setup-time helpers for FS-Finanzportal.
 */

function fsfp_cli_workflow_types(string $slug): array
{
    return [
        'beschluss' => "b_{$slug}",
        'zahlung' => "za_{$slug}",
    ];
}

function fsfp_cli_capability_type(string $post_type): string
{
    return "{$post_type}_record";
}

function fsfp_cli_global_access_roles(): array
{
    return fsfp_cli_roles_config()['global_access_roles'];
}

function fsfp_cli_global_overview_roles(): array
{
    return fsfp_cli_roles_config()['global_overview_roles'];
}

function fsfp_cli_global_edit_roles(): array
{
    return fsfp_cli_global_zahlung_edit_roles();
}

function fsfp_cli_global_beschluss_edit_roles(): array
{
    return fsfp_cli_roles_config()['global_beschluss_edit_roles'];
}

function fsfp_cli_global_zahlung_edit_roles(): array
{
    return fsfp_cli_roles_config()['global_zahlung_edit_roles'];
}

function fsfp_cli_fachschaft_access_roles(string $slug): array
{
    return array_merge([
        "fs_{$slug}_reader",
        "fs_{$slug}_finance",
    ], fsfp_cli_global_access_roles());
}

function fsfp_cli_fachschaft_view_roles(string $slug): array
{
    return [
        "fs_{$slug}_reader",
        "fs_{$slug}_finance",
        'administrator',
        'portal_admin',
        'auditor',
    ];
}

function fsfp_cli_admin_edit_access_cap(): string
{
    return (string) fsfp_cli_roles_config()['admin_edit_access_cap'];
}

function fsfp_cli_roles_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = fsfp_cli_load_json_config('portal/roles.json', 'Portal roles config JSON is invalid.');
    }

    return $config;
}
