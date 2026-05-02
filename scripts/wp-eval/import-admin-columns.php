<?php
/**
 * Best-effort Admin Columns import for the Beschluss list table.
 *
 * The free Admin Columns plugin documents JSON import/export primarily through
 * the admin UI. This helper stores the versioned configuration in WordPress and
 * writes legacy option shapes used by older Admin Columns releases. If the
 * installed plugin exposes a stable importer, this file is the narrow place to
 * swap in that API without turning the prototype into a persistent plugin.
 */

$config_file = $args[0] ?? null;

if (!$config_file || !is_readable($config_file)) {
    WP_CLI::error('Admin Columns config file is missing or unreadable.');
}

$config = json_decode(file_get_contents($config_file), true);

if (!is_array($config) || empty($config['columns'])) {
    WP_CLI::error('Admin Columns config JSON is invalid.');
}

update_option('fs_finanzportal_admin_columns_beschluss', $config, false);

$legacy_columns = [];

foreach ($config['columns'] as $column) {
    $name = $column['name'] ?? sanitize_key($column['label'] ?? uniqid('column_', false));
    $legacy_columns[$name] = [
        'type' => $column['type'] ?? 'column-meta',
        'label' => $column['label'] ?? $name,
        'width' => $column['width'] ?? '',
    ];

    if (!empty($column['meta_key'])) {
        $legacy_columns[$name]['field'] = $column['meta_key'];
        $legacy_columns[$name]['meta_key'] = $column['meta_key'];
        $legacy_columns[$name]['field_type'] = $column['field_type'] ?? 'text';
    }
}

update_option('cpac_options_beschluss', $legacy_columns, false);
update_option('cpac_options_wp-posts_beschluss', $legacy_columns, false);

if (!function_exists('ac')) {
    WP_CLI::warning('Admin Columns API function ac() is unavailable; stored config for manual/plugin import fallback.');
    return;
}

WP_CLI::success('Stored Admin Columns Beschluss configuration.');
