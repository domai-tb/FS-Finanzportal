<?php
/**
 * Setup-time Pods helpers for FS-Finanzportal.
 */

function fsfp_base_pod(string $name, string $label, string $singular, string $capability_type, string $icon, int $position): array
{
    return [
        'name' => $name,
        'label' => $label,
        'label_singular' => $singular,
        'storage' => 'meta',
        'public' => 1,
        'publicly_queryable' => 0,
        'exclude_from_search' => 1,
        'show_ui' => 1,
        'show_in_menu' => 1,
        'show_in_nav_menus' => 0,
        'show_in_admin_bar' => 1,
        'show_in_rest' => 0,
        'has_archive' => 0,
        'rewrite' => 0,
        'query_var' => 0,
        'capability_type' => 'custom',
        'capability_type_custom' => $capability_type,
        'capability_type_extra' => 1,
        'supports_title' => 1,
        'supports_author' => 1,
        'supports_revisions' => 1,
        'supports_editor' => 0,
        'supports_thumbnail' => 0,
        'menu_position' => $position,
        'menu_icon' => $icon,
        'built_in_post_status' => 'private,draft,pending,publish',
        'default_status' => 'publish',
    ];
}

function fsfp_pods_option_lines(array $options): string
{
    $lines = [];
    foreach ($options as $value => $label) {
        $lines[] = "{$value}|{$label}";
    }

    return implode("\n", $lines);
}

function fsfp_pods_field_from_config(array $field, string $beschluss_type): array
{
    $type = (string) ($field['type'] ?? '');
    $name = (string) ($field['name'] ?? '');
    $label = (string) ($field['label'] ?? $name);
    $required = (bool) ($field['required'] ?? false);

    switch ($type) {
        case 'text':
            return fsfp_text_field($name, $label, $required);
        case 'paragraph':
            return fsfp_paragraph_field($name, $label, $required);
        case 'date':
            return fsfp_date_field($name, $label, $required);
        case 'currency':
            return fsfp_currency_field();
        case 'file':
            return fsfp_file_field();
        case 'status':
            return fsfp_status_field($name, $label, fsfp_pods_option_lines($field['options'] ?? []));
        case 'pick':
            return fsfp_pick_field(
                $name,
                $label,
                fsfp_pods_option_lines($field['options'] ?? []),
                (string) ($field['default'] ?? ''),
                $required
            );
        case 'beschluss_reference':
            return fsfp_beschluss_reference_field($beschluss_type);
    }

    WP_CLI::error("Unsupported Pods field type {$type} for {$name}.");
}

function fsfp_pods_fields_from_config(array $fields, string $beschluss_type): array
{
    return array_map(
        fn(array $field) => fsfp_pods_field_from_config($field, $beschluss_type),
        $fields
    );
}

function fsfp_delete_legacy_pods(): void
{
    $api = $GLOBALS['fsfp_pods_api'] ?? null;
    if (!$api) {
        WP_CLI::error('Pods API is unavailable inside importer.');
    }

    foreach (['beschluss', 'zahlungsanweisung'] as $legacy_pod) {
        $existing = $api->load_pod(['name' => $legacy_pod]);
        if (!empty($existing['id'])) {
            $api->delete_pod(['id' => $existing['id']], false, false);
        }
    }
}

function fsfp_save_fachschaft_pod(): void
{
    fsfp_save_pod([
        'name' => 'fachschaft',
        'label' => 'Fachschaften',
        'label_singular' => 'Fachschaft',
        'storage' => 'meta',
        'public' => 0,
        'show_ui' => 1,
        'show_in_menu' => 1,
        'show_in_nav_menus' => 0,
        'show_in_admin_bar' => 1,
        'show_in_rest' => 0,
        'capability_type' => 'custom',
        'capability_type_custom' => 'fachschaft_record',
        'capability_type_extra' => 1,
        'supports_title' => 1,
        'supports_editor' => 0,
        'supports_thumbnail' => 0,
        'menu_position' => 24,
        'menu_icon' => 'dashicons-groups',
        'built_in_post_status' => 'publish,draft,private',
        'fields' => [],
    ]);
}

function fsfp_import_scoped_pods(array $fachschaften, array $pods_config): void
{
    fsfp_delete_legacy_pods();
    fsfp_save_fachschaft_pod();

    $position = 25;
    $workflow_config = $pods_config['workflows'] ?? [];

    foreach ($fachschaften as $fachschaft) {
        $slug = sanitize_key($fachschaft['slug'] ?? '');
        $short_label = $fachschaft['short_label'] ?? ucfirst($slug);

        if ($slug === '') {
            WP_CLI::error('Fachschaft slug must not be empty.');
        }

        $beschluss_type = "b_{$slug}";
        $zahlung_type = "za_{$slug}";

        if (strlen($beschluss_type) > 20 || strlen($zahlung_type) > 20) {
            WP_CLI::error("Fachschaft slug {$slug} is too long for scoped WordPress post type names.");
        }

        $beschluss = fsfp_base_pod(
            $beschluss_type,
            "Beschlüsse: {$short_label}",
            "Beschluss: {$short_label}",
            "{$beschluss_type}_record",
            $workflow_config['beschluss']['icon'] ?? 'dashicons-portfolio',
            $position++
        );
        $beschluss['fields'] = fsfp_pods_fields_from_config($workflow_config['beschluss']['fields'] ?? [], $beschluss_type);
        fsfp_save_pod($beschluss);
        fsfp_delete_pod_field($beschluss_type, 'zahlungsanweisung_ref');

        $zahlung = fsfp_base_pod(
            $zahlung_type,
            "Zahlungsanweisungen: {$short_label}",
            "Zahlungsanweisung: {$short_label}",
            "{$zahlung_type}_record",
            $workflow_config['zahlung']['icon'] ?? 'dashicons-money-alt',
            $position++
        );
        $zahlung['fields'] = fsfp_pods_fields_from_config($workflow_config['zahlung']['fields'] ?? [], $beschluss_type);
        fsfp_save_pod($zahlung);
    }
}
