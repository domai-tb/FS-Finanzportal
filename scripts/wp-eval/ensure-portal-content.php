<?php
/**
 * Idempotently creates roles, an admin entry page, Fachschaften, demo users,
 * and demo data for the configured-plugin prototype.
 */

function fsfp_cli_role(string $role, string $label, string $clone = ''): void
{
    if (!get_role($role)) {
        $caps = [];
        if ($clone !== '') {
            $clone_role = get_role($clone);
            $caps = $clone_role ? $clone_role->capabilities : [];
        }
        add_role($role, $label, $caps);
    }
}

function fsfp_cli_add_caps(string $role, array $caps): void
{
    $role_obj = get_role($role);
    if (!$role_obj) {
        WP_CLI::error("Role {$role} is missing.");
    }

    foreach ($caps as $cap) {
        $role_obj->add_cap($cap);
    }
}

function fsfp_cli_sync_caps(string $role, array $caps): void
{
    $role_obj = get_role($role);
    if (!$role_obj) {
        WP_CLI::error("Role {$role} is missing.");
    }

    foreach (array_keys($role_obj->capabilities) as $cap) {
        $role_obj->remove_cap($cap);
    }

    fsfp_cli_add_caps($role, $caps);
}

function fsfp_cli_post_type_caps(string $capability_type): array
{
    $plural = "{$capability_type}s";

    return [
        "read_{$capability_type}",
        "edit_{$capability_type}",
        "delete_{$capability_type}",
        "read_private_{$plural}",
        "edit_{$plural}",
        "edit_others_{$plural}",
        "edit_private_{$plural}",
        "edit_published_{$plural}",
        "publish_{$plural}",
        "delete_{$plural}",
        "delete_private_{$plural}",
        "delete_published_{$plural}",
        "delete_others_{$plural}",
    ];
}

function fsfp_cli_own_post_type_caps(string $capability_type): array
{
    $plural = "{$capability_type}s";

    return [
        "read_{$capability_type}",
        "edit_{$capability_type}",
        "delete_{$capability_type}",
        "edit_{$plural}",
        "edit_published_{$plural}",
        "publish_{$plural}",
        "delete_{$plural}",
        "delete_published_{$plural}",
    ];
}

function fsfp_cli_upsert_post(string $post_type, string $slug, string $title, array $extra = []): int
{
    $existing = get_page_by_path($slug, OBJECT, $post_type);
    $post = array_merge([
        'post_type' => $post_type,
        'post_title' => $title,
        'post_name' => $slug,
        'post_status' => 'publish',
    ], $extra);

    if ($existing) {
        $post['ID'] = $existing->ID;
        $post_id = wp_update_post($post, true);
    } else {
        $post_id = wp_insert_post($post, true);
    }

    if (is_wp_error($post_id)) {
        WP_CLI::error($post_id->get_error_message());
    }

    return (int) $post_id;
}

function fsfp_cli_upsert_user(string $login, string $email, string $role): int
{
    $user = get_user_by('login', $login);
    if (!$user) {
        $user_id = wp_create_user($login, 'demo_secret', $email);
        if (is_wp_error($user_id)) {
            WP_CLI::error($user_id->get_error_message());
        }
        $user = get_user_by('id', (int) $user_id);
    }

    $user->set_role($role);
    wp_update_user([
        'ID' => $user->ID,
        'display_name' => ucwords(str_replace('-', ' ', $login)),
        'user_email' => $email,
    ]);

    delete_user_meta($user->ID, 'fsfp_fachschaften');

    return (int) $user->ID;
}

fsfp_cli_role('portal_admin', 'Portal Admin', 'administrator');
fsfp_cli_role('asta_finance', 'AStA Finance');
fsfp_cli_role('asta_reviewer', 'AStA Reviewer');
fsfp_cli_role('fachschaft_finance', 'Fachschaft Finance');
fsfp_cli_role('fachschaft_reader', 'Fachschaft Reader');
fsfp_cli_role('auditor', 'Auditor');

$fachschaft_caps = fsfp_cli_post_type_caps('fachschaft_record');
$beschluss_caps = fsfp_cli_post_type_caps('beschluss_record');
$zahlungsanweisung_caps = fsfp_cli_post_type_caps('zahlungsanweisung_record');
$workflow_caps = array_merge($beschluss_caps, $zahlungsanweisung_caps);
$administrator_caps = get_role('administrator') ? array_keys(get_role('administrator')->capabilities) : ['read', 'manage_options'];

fsfp_cli_sync_caps('portal_admin', array_values(array_unique(array_merge(
    $administrator_caps,
    $fachschaft_caps,
    $workflow_caps
))));
fsfp_cli_sync_caps('asta_finance', array_values(array_unique(array_merge(
    ['read', 'upload_files'],
    $workflow_caps
))));
fsfp_cli_sync_caps('asta_reviewer', [
    'read',
    'read_beschluss_record',
    'read_private_beschluss_records',
    'edit_beschluss_record',
    'edit_beschluss_records',
    'edit_others_beschluss_records',
    'edit_published_beschluss_records',
    'read_zahlungsanweisung_record',
    'read_private_zahlungsanweisung_records',
    'edit_zahlungsanweisung_record',
    'edit_zahlungsanweisung_records',
    'edit_others_zahlungsanweisung_records',
    'edit_published_zahlungsanweisung_records',
]);
fsfp_cli_sync_caps('fachschaft_finance', array_values(array_unique(array_merge(
    ['read', 'upload_files'],
    fsfp_cli_own_post_type_caps('beschluss_record'),
    fsfp_cli_own_post_type_caps('zahlungsanweisung_record')
))));
fsfp_cli_sync_caps('fachschaft_reader', ['read']);
fsfp_cli_sync_caps('auditor', ['read']);

$dashboard = get_page_by_path('dashboard', OBJECT, 'page');
$dashboard_content = '<!-- wp:heading --><h2>Fachschaftsfinanzen</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Die Workflow-Verwaltung erfolgt in WordPress über die konfigurierten Inhaltstypen, Rollen, Pods-Felder und Listenansichten.</p><!-- /wp:paragraph -->
<!-- wp:list --><ul><li><a href="/wp-admin/edit.php?post_type=beschluss">Beschlüsse verwalten</a></li><li><a href="/wp-admin/post-new.php?post_type=beschluss">Beschluss erstellen</a></li><li><a href="/wp-admin/edit.php?post_type=zahlungsanweisung">Zahlungsanweisungen verwalten</a></li></ul><!-- /wp:list -->';
if ($dashboard) {
    wp_update_post([
        'ID' => $dashboard->ID,
        'post_title' => 'Dashboard',
        'post_name' => 'dashboard',
        'post_content' => $dashboard_content,
        'post_status' => 'publish',
    ]);
} else {
    wp_insert_post([
        'post_type' => 'page',
        'post_title' => 'Dashboard',
        'post_name' => 'dashboard',
        'post_content' => $dashboard_content,
        'post_status' => 'publish',
    ]);
}

$fachschaften = [
    'informatik' => 'Fachschaft Informatik',
    'philosophie' => 'Fachschaft Philosophie',
    'maschinenbau' => 'Fachschaft Maschinenbau',
];

foreach ($fachschaften as $slug => $title) {
    fsfp_cli_upsert_post('fachschaft', $slug, $title);
}

$users = [
    ['demo-fachschaft', 'demo-fachschaft@example.com', 'fachschaft_finance'],
    ['demo-informatik-reader', 'demo-informatik-reader@example.com', 'fachschaft_reader'],
    ['demo-informatik-reader2', 'demo-informatik-reader2@example.com', 'fachschaft_reader'],
    ['demo-maschinenbau-finance', 'demo-maschinenbau-finance@example.com', 'fachschaft_finance'],
    ['demo-maschinenbau-reader', 'demo-maschinenbau-reader@example.com', 'fachschaft_reader'],
    ['demo-maschinenbau-reader2', 'demo-maschinenbau-reader2@example.com', 'fachschaft_reader'],
    ['demo-philosophie-finance', 'demo-philosophie-finance@example.com', 'fachschaft_finance'],
    ['demo-philosophie', 'demo-philosophie@example.com', 'fachschaft_reader'],
    ['demo-philosophie-reader2', 'demo-philosophie-reader2@example.com', 'fachschaft_reader'],
    ['demo-asta', 'demo-asta@example.com', 'asta_finance'],
    ['demo-reviewer', 'demo-reviewer@example.com', 'asta_reviewer'],
    ['demo-auditor', 'demo-auditor@example.com', 'auditor'],
];

foreach ($users as $user) {
    fsfp_cli_upsert_user($user[0], $user[1], $user[2]);
}

$file = getenv('WP_CONFIG_DIR') . '/demo/beschluesse.json';
$items = json_decode(file_get_contents($file), true);
if (!is_array($items)) {
    WP_CLI::error('Invalid demo Beschluesse JSON.');
}

foreach ($items as $item) {
    $author = get_user_by('login', $item['author'] ?? 'demo-fachschaft');
    $post_id = fsfp_cli_upsert_post('beschluss', $item['slug'], $item['title'], [
        'post_author' => $author ? $author->ID : 1,
    ]);

    foreach (['fachschaft', 'beschlussdatum', 'betrag', 'zweck_beschreibung', 'zahlungsanweisung_ref', 'notes'] as $field) {
        update_post_meta($post_id, $field, $item[$field] ?? '');
    }

    update_post_meta($post_id, 'beschluss_status', $item['status'] ?? 'draft');

}

WP_CLI::success('Portal content configured.');
