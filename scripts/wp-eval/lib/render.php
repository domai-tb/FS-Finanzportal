<?php
/**
 * Minimal setup-time placeholder rendering for editable template fragments.
 */

function fsfp_cli_render_template(string $template, array $values): string
{
    $replacements = [];
    foreach ($values as $key => $value) {
        $replacements['{{' . $key . '}}'] = (string) $value;
    }

    return strtr($template, $replacements);
}

function fsfp_cli_render_config_template(string $relative_path, array $values = []): string
{
    return fsfp_cli_render_template(fsfp_cli_read_config_file($relative_path), $values);
}
