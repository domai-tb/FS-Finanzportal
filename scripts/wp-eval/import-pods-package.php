<?php
/**
 * Imports a Pods package when the Pods WP-CLI command is unavailable.
 */

$config_file = $args[0] ?? null;

if (!$config_file || !is_readable($config_file)) {
    WP_CLI::error('Pods package file is missing or unreadable.');
}

if (!function_exists('pods_api')) {
    WP_CLI::error('Pods API is unavailable.');
}

$json = file_get_contents($config_file);
$package = json_decode($json, true);

if (!is_array($package) || empty($package['pods'])) {
    WP_CLI::error('Pods package JSON is invalid.');
}

$api = pods_api();

foreach ($package['pods'] as $pod) {
    if (($pod['type'] ?? '') !== 'post_type') {
        WP_CLI::error('Only post_type Pods are supported by this importer.');
    }

    $existing = $api->load_pod(['name' => $pod['name']]);

    if (!empty($existing) && ($existing['type'] ?? '') !== 'post_type') {
        WP_CLI::warning("Replacing incompatible existing Pod {$pod['name']}.");
        $api->delete_pod(['id' => $existing['id']], false, false);
        $existing = null;
    }

    $fields = $pod['fields'] ?? [];
    unset($pod['fields']);

    if (!empty($existing['id'])) {
        $pod['id'] = $existing['id'];
    }

    $pod['create_extend'] = 'create';
    $pod['storage'] = $pod['storage'] ?? 'meta';

    $pod_id = $api->save_pod($pod);

    if (is_wp_error($pod_id)) {
        WP_CLI::error($pod_id->get_error_message());
    }

    if (!$pod_id) {
        WP_CLI::error("Could not save Pod {$pod['name']}.");
    }

    $weight = 0;

    foreach ($fields as $field) {
        $existing_field = $api->load_field([
            'pod' => $pod['name'],
            'name' => $field['name'],
        ]);

        if (!empty($existing_field['id'])) {
            $field['id'] = $existing_field['id'];
        }

        $field['pod_id'] = $pod_id;
        $field['pod'] = $pod['name'];
        $field['weight'] = $weight++;

        $field_id = $api->save_field($field, false);

        if (is_wp_error($field_id)) {
            WP_CLI::error($field_id->get_error_message());
        }

        if (!$field_id) {
            WP_CLI::error("Could not save field {$field['name']}.");
        }
    }
}

if (function_exists('pods_cache_clear')) {
    pods_cache_clear();
}

WP_CLI::success('Pods package imported.');
