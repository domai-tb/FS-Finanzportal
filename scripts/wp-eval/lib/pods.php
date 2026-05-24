<?php
/**
 * Setup-time Pods helpers for FS-Finanzportal.
 */

function fsfp_save_pod(array $pod): void
{
    $api = $GLOBALS['fsfp_pods_api'] ?? null;

    if (!$api) {
        WP_CLI::error('Pods API is unavailable inside importer.');
    }

    $fields = $pod['fields'] ?? [];
    unset($pod['fields']);

    $existing = $api->load_pod(['name' => $pod['name']]);

    if (!empty($existing) && ($existing['type'] ?? '') !== 'post_type') {
        WP_CLI::warning("Replacing incompatible existing Pod {$pod['name']}.");
        $api->delete_pod(['id' => $existing['id']], false, false);
        $existing = null;
    }

    if (!empty($existing['id'])) {
        $pod['id'] = $existing['id'];
    }

    $pod['type'] = 'post_type';
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

function fsfp_delete_pod_field(string $pod_name, string $field_name): void
{
    $api = $GLOBALS['fsfp_pods_api'] ?? null;

    if (!$api) {
        WP_CLI::error('Pods API is unavailable inside importer.');
    }

    $field = $api->load_field([
        'pod' => $pod_name,
        'name' => $field_name,
    ]);

    if (!empty($field['id'])) {
        $deleted = $api->delete_field([
            'id' => $field['id'],
            'pod' => $pod_name,
        ]);

        if (is_wp_error($deleted)) {
            WP_CLI::error($deleted->get_error_message());
        }
    }
}
