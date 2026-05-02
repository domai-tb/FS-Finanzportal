<?php
/**
 * Idempotently creates the portal page, Fachschaften, demo users, and demo data.
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

function fsfp_cli_upsert_user(string $login, string $email, string $role, string $fachschaft = ''): int
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

    if ($fachschaft !== '') {
        update_user_meta($user->ID, 'fsfp_fachschaft', sanitize_title($fachschaft));
    } else {
        delete_user_meta($user->ID, 'fsfp_fachschaft');
    }

    return (int) $user->ID;
}

fsfp_cli_role('portal_admin', 'Portal Admin', 'administrator');
fsfp_cli_role('asta_finance', 'AStA Finance');
fsfp_cli_role('asta_reviewer', 'AStA Reviewer');
fsfp_cli_role('fachschaft_finance', 'Fachschaft Finance');
fsfp_cli_role('fachschaft_reader', 'Fachschaft Reader');
fsfp_cli_role('auditor', 'Auditor');

fsfp_cli_add_caps('portal_admin', ['read', 'upload_files', 'edit_posts', 'edit_others_posts', 'edit_published_posts', 'publish_posts', 'delete_posts', 'delete_others_posts', 'delete_published_posts', 'manage_options']);
fsfp_cli_add_caps('asta_finance', ['read', 'upload_files', 'edit_posts', 'edit_others_posts', 'edit_published_posts', 'publish_posts', 'delete_posts', 'delete_others_posts', 'delete_published_posts']);
fsfp_cli_add_caps('asta_reviewer', ['read', 'edit_posts', 'edit_others_posts', 'edit_published_posts']);
fsfp_cli_add_caps('fachschaft_finance', ['read', 'upload_files', 'edit_posts', 'edit_published_posts', 'publish_posts', 'delete_posts', 'delete_published_posts']);
fsfp_cli_add_caps('fachschaft_reader', ['read']);
fsfp_cli_add_caps('auditor', ['read']);

$dashboard = get_page_by_path('dashboard', OBJECT, 'page');
$dashboard_content = '<!-- wp:shortcode -->[fs_finanzportal_dashboard]<!-- /wp:shortcode -->';
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
    ['demo-fachschaft', 'demo-fachschaft@example.com', 'fachschaft_finance', 'informatik'],
    ['demo-philosophie', 'demo-philosophie@example.com', 'fachschaft_reader', 'philosophie'],
    ['demo-asta', 'demo-asta@example.com', 'asta_finance', ''],
    ['demo-reviewer', 'demo-reviewer@example.com', 'asta_reviewer', ''],
    ['demo-auditor', 'demo-auditor@example.com', 'auditor', ''],
];

foreach ($users as $user) {
    fsfp_cli_upsert_user($user[0], $user[1], $user[2], $user[3]);
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

    if (!get_post_meta($post_id, '_fsfp_status_history')) {
        add_post_meta($post_id, '_fsfp_status_history', [
            'from' => '',
            'to' => $item['status'] ?? 'draft',
            'user_id' => 0,
            'user' => 'Setup',
            'timestamp' => current_time('mysql'),
            'comment' => 'Demo-Datensatz',
        ]);
    }
}

WP_CLI::success('Portal content configured.');
