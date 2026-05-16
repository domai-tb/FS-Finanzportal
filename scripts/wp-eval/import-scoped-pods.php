<?php
/**
 * Builds and imports the Fachschaft-scoped Pods model from configuration.
 *
 * This is setup-time code only. It keeps the running WordPress site free of
 * project-specific plugins while still making the repeated Pods configuration
 * reproducible.
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

$api = pods_api();
$GLOBALS['fsfp_pods_api'] = $api;

function fsfp_scoped_caps(string $post_type): string
{
    return "{$post_type}_record";
}

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

function fsfp_text_field(string $name, string $label, bool $required = false): array
{
    return [
        'name' => $name,
        'label' => $label,
        'type' => 'text',
        'required' => $required ? 1 : 0,
    ];
}

function fsfp_paragraph_field(string $name, string $label): array
{
    return [
        'name' => $name,
        'label' => $label,
        'type' => 'paragraph',
        'paragraph_allow_html' => 0,
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

function fsfp_beschluss_reference_field(string $beschluss_type): array
{
    return [
        'name' => 'beschluss_ref',
        'label' => 'Beschluss reference',
        'type' => 'pick',
        'pick_object' => 'post_type',
        'pick_val' => $beschluss_type,
        'pick_format_type' => 'single',
        'pick_format_single' => 'dropdown',
        'pick_display' => 'post_title',
        'pick_where' => "beschluss_status.meta_value = 'approved'",
        'required' => 1,
        'description' => 'Nur genehmigte Beschlüsse dürfen fachlich referenziert werden.',
    ];
}

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

foreach (['beschluss', 'zahlungsanweisung'] as $legacy_pod) {
    $existing = $api->load_pod(['name' => $legacy_pod]);
    if (!empty($existing['id'])) {
        $api->delete_pod(['id' => $existing['id']], false, false);
    }
}

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

$position = 25;

foreach ($config['fachschaften'] as $fachschaft) {
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
        fsfp_scoped_caps($beschluss_type),
        'dashicons-portfolio',
        $position++
    );
    $beschluss['fields'] = [
        fsfp_text_field('fachschaft', 'Fachschaft'),
        [
            'name' => 'beschlussdatum',
            'label' => 'Beschlussdatum',
            'type' => 'date',
            'date_format' => 'ymd_dash',
            'required' => 1,
        ],
        fsfp_currency_field(),
        fsfp_paragraph_field('zweck_beschreibung', 'Zweck / Beschreibung'),
        fsfp_status_field('beschluss_status', 'Status', "draft|Entwurf\napproved|Genehmigt\nrejected|Abgelehnt"),
        fsfp_date_field('decided_at', 'Entschieden am'),
        fsfp_text_field('decided_by', 'Entschieden durch'),
        fsfp_paragraph_field('decision_note', 'Entscheidungshinweis'),
        fsfp_file_field(),
        fsfp_paragraph_field('notes', 'Notizen / Rückfragen'),
    ];
    fsfp_save_pod($beschluss);
    fsfp_delete_pod_field($beschluss_type, 'zahlungsanweisung_ref');

    $zahlung = fsfp_base_pod(
        $zahlung_type,
        "Zahlungsanweisungen: {$short_label}",
        "Zahlungsanweisung: {$short_label}",
        fsfp_scoped_caps($zahlung_type),
        'dashicons-money-alt',
        $position++
    );
    $zahlung['fields'] = [
        fsfp_text_field('fachschaft', 'Fachschaft'),
        fsfp_currency_field(),
        fsfp_paragraph_field('verwendungszweck', 'Verwendungszweck'),
        fsfp_status_field('zahlungs_status', 'Status', "draft|Entwurf\nsubmitted|Eingereicht\ncorrection_requested|Rückfrage\ncancelled|Stoniert\nexecuted|Ausgeführt"),
        fsfp_date_field('submitted_at', 'Eingereicht am'),
        fsfp_date_field('reviewed_at', 'Geprüft am'),
        fsfp_text_field('reviewed_by', 'Geprüft durch'),
        fsfp_date_field('executed_at', 'Ausgeführt am'),
        fsfp_text_field('executed_by', 'Ausgeführt durch'),
        fsfp_paragraph_field('workflow_note', 'Workflowhinweis'),
        fsfp_file_field(),
        fsfp_beschluss_reference_field($beschluss_type),
        fsfp_paragraph_field('notes', 'Notizen / Rückfragen'),
    ];
    fsfp_save_pod($zahlung);
}

if (function_exists('pods_cache_clear')) {
    pods_cache_clear();
}

WP_CLI::success('Scoped Pods package imported.');
