<?php
/**
 * Setup-time helpers for FS-Finanzportal.
 */

function fsfp_cli_config_path(string $relative_path): string
{
    return rtrim((string) getenv('WP_CONFIG_DIR'), '/') . '/' . ltrim($relative_path, '/');
}

function fsfp_cli_load_fachschaften(): array
{
    $file = fsfp_cli_config_path('fachschaften.json');
    $config = json_decode(file_get_contents($file), true);

    if (!is_array($config) || empty($config['fachschaften']) || !is_array($config['fachschaften'])) {
        WP_CLI::error('Invalid Fachschaften JSON.');
    }

    return $config['fachschaften'];
}

function fsfp_cli_load_json_config(string $relative_path, string $error_message): array
{
    $file = fsfp_cli_config_path($relative_path);
    $config = json_decode(file_get_contents($file), true);

    if (!is_array($config)) {
        WP_CLI::error($error_message);
    }

    return $config;
}

function fsfp_cli_read_config_file(string $relative_path): string
{
    $file = fsfp_cli_config_path($relative_path);
    if (!is_readable($file)) {
        WP_CLI::error("Config file {$relative_path} is missing or unreadable.");
    }

    return (string) file_get_contents($file);
}
