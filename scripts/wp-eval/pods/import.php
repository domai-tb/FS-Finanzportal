<?php
/**
 * Main setup-time scoped Pods import orchestration.
 */

$config_file = $args[0] ?? null;

if (!$config_file || !is_readable($config_file)) {
    WP_CLI::error('Fachschaften config file is missing or unreadable.');
}

if (!function_exists('pods_api')) {
    WP_CLI::error('Pods API is unavailable.');
}

$config = json_decode(file_get_contents($config_file), true);

if (!is_array($config) || empty($config['fachschaften']) || !is_array($config['fachschaften'])) {
    WP_CLI::error('Fachschaften config JSON is invalid.');
}

$GLOBALS['fsfp_pods_api'] = pods_api();

$pods_config = fsfp_cli_load_json_config('portal/pods.json', 'Portal Pods config JSON is invalid.');
fsfp_import_scoped_pods($config['fachschaften'], $pods_config);

if (function_exists('pods_cache_clear')) {
    pods_cache_clear();
}

WP_CLI::success('Scoped Pods package imported.');
