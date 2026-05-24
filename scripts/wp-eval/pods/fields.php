<?php
/**
 * Setup-time Pods helpers for FS-Finanzportal.
 */

function fsfp_text_field(string $name, string $label, bool $required = false): array
{
    return [
        'name' => $name,
        'label' => $label,
        'type' => 'text',
        'required' => $required ? 1 : 0,
    ];
}

function fsfp_paragraph_field(string $name, string $label, bool $required = false): array
{
    return [
        'name' => $name,
        'label' => $label,
        'type' => 'paragraph',
        'paragraph_allow_html' => 0,
        'required' => $required ? 1 : 0,
    ];
}

function fsfp_date_field(string $name, string $label, bool $required = false): array
{
    return [
        'name' => $name,
        'label' => $label,
        'type' => 'date',
        'date_format' => 'ymd_dash',
        'required' => $required ? 1 : 0,
    ];
}

function fsfp_currency_field(): array
{
    return [
        'name' => 'betrag',
        'label' => 'Betrag',
        'type' => 'currency',
        'currency_format_type' => 'number',
        'currency_decimals' => 2,
        'currency_decimal_handling' => 'i18n',
        'currency_decimal_point' => ',',
        'currency_thousands' => '.',
        'currency_symbol' => '€',
        'required' => 1,
    ];
}

function fsfp_file_field(): array
{
    return [
        'name' => 'belege',
        'label' => 'Belege / Anhänge',
        'type' => 'file',
        'file_format_type' => 'multi',
        'file_uploader' => 'attachment',
        'file_attachment_tab' => 'upload',
        'repeatable' => 1,
    ];
}

function fsfp_status_field(string $name, string $label, string $values): array
{
    return [
        'name' => $name,
        'label' => $label,
        'type' => 'pick',
        'pick_object' => 'custom-simple',
        'pick_format_type' => 'single',
        'pick_format_single' => 'dropdown',
        'pick_custom' => $values,
        'default_value' => 'draft',
        'required' => 1,
    ];
}

function fsfp_pick_field(string $name, string $label, string $values, string $default_value = '', bool $required = false): array
{
    return [
        'name' => $name,
        'label' => $label,
        'type' => 'pick',
        'pick_object' => 'custom-simple',
        'pick_format_type' => 'single',
        'pick_format_single' => 'dropdown',
        'pick_custom' => $values,
        'default_value' => $default_value,
        'required' => $required ? 1 : 0,
    ];
}

function fsfp_beschluss_reference_field(string $beschluss_type): array
{
    return [
        'name' => 'beschluss_ref',
        'label' => 'Beschluss',
        'type' => 'pick',
        'pick_object' => 'post_type',
        'pick_val' => $beschluss_type,
        'pick_format_type' => 'single',
        'pick_format_single' => 'dropdown',
        'pick_display' => 'post_title',
        'pick_where' => "beschluss_status.meta_value = 'approved'",
        'required' => 0,
        'description' => 'Nur genehmigte Beschlüsse dürfen fachlich referenziert werden. Bei Vorkasse bleibt dieses Feld leer.',
    ];
}
