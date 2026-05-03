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

function fsfp_cli_upsert_page(string $slug, string $title, string $content, int $parent_id = 0): int
{
    return fsfp_cli_upsert_post('page', $slug, $title, [
        'post_content' => $content,
        'post_parent' => $parent_id,
    ]);
}

function fsfp_cli_ensure_menu(string $menu_name, array $items): void
{
    $menu = wp_get_nav_menu_object($menu_name);

    if (!$menu) {
        $menu_id = wp_create_nav_menu($menu_name);
    } else {
        $menu_id = (int) $menu->term_id;
        foreach (wp_get_nav_menu_items($menu_id) ?: [] as $item) {
            wp_delete_post($item->ID, true);
        }
    }

    foreach ($items as $item) {
        wp_update_nav_menu_item($menu_id, 0, [
            'menu-item-title' => $item['title'],
            'menu-item-url' => $item['url'],
            'menu-item-status' => 'publish',
        ]);
    }

    update_option('nav_menu_options', [
        'auto_add' => [],
    ]);

    $locations = get_theme_mod('nav_menu_locations', []);
    $registered_locations = get_registered_nav_menus();

    foreach (array_keys($registered_locations) as $location) {
        if (!isset($locations[$location])) {
            $locations[$location] = $menu_id;
            break;
        }
    }

    set_theme_mod('nav_menu_locations', $locations);
}

function fsfp_cli_portal_nav_items(): string
{
    return '<!-- wp:navigation-link {"label":"Dashboard","url":"/dashboard/"} /--><!-- wp:navigation-link {"label":"Beschlüsse","url":"/dashboard/beschluesse/"} /--><!-- wp:navigation-link {"label":"Zahlungsanweisungen","url":"/dashboard/zahlungsanweisungen/"} /--><!-- wp:navigation-link {"label":"Logout","url":"/wp-login.php?action=logout"} /-->';
}

function fsfp_cli_portal_nav(): string
{
    return '<!-- wp:navigation -->' . fsfp_cli_portal_nav_items() . '<!-- /wp:navigation -->';
}

function fsfp_cli_ensure_block_navigation(string $title): void
{
    if (!post_type_exists('wp_navigation')) {
        return;
    }

    $content = fsfp_cli_portal_nav_items();
    $existing = get_page_by_path(sanitize_title($title), OBJECT, 'wp_navigation');

    if ($existing) {
        wp_update_post([
            'ID' => $existing->ID,
            'post_title' => $title,
            'post_name' => sanitize_title($title),
            'post_content' => $content,
            'post_status' => 'publish',
        ]);
        return;
    }

    $navigation_posts = get_posts([
        'post_type' => 'wp_navigation',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);

    if (!empty($navigation_posts)) {
        wp_update_post([
            'ID' => (int) $navigation_posts[0],
            'post_title' => $title,
            'post_name' => sanitize_title($title),
            'post_content' => $content,
            'post_status' => 'publish',
        ]);

        foreach (array_slice($navigation_posts, 1) as $post_id) {
            wp_delete_post((int) $post_id, true);
        }

        return;
    }

    wp_insert_post([
        'post_type' => 'wp_navigation',
        'post_title' => $title,
        'post_name' => sanitize_title($title),
        'post_content' => $content,
        'post_status' => 'publish',
    ]);
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
fsfp_cli_role('fsr_member', 'FSR Member');
fsfp_cli_role('fsr_treasurer', 'FSR Treasurer');
fsfp_cli_role('fsr_board', 'FSR Board');
fsfp_cli_role('asta_finance_admin', 'AStA Finance Admin');

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
fsfp_cli_sync_caps('fsr_member', ['read']);
fsfp_cli_sync_caps('fsr_treasurer', array_values(array_unique(array_merge(
    ['read', 'upload_files'],
    fsfp_cli_own_post_type_caps('beschluss_record'),
    fsfp_cli_own_post_type_caps('zahlungsanweisung_record')
))));
fsfp_cli_sync_caps('fsr_board', [
    'read',
    'read_beschluss_record',
    'read_private_beschluss_records',
    'edit_beschluss_record',
    'edit_beschluss_records',
    'edit_published_beschluss_records',
    'read_zahlungsanweisung_record',
    'read_private_zahlungsanweisung_records',
    'edit_zahlungsanweisung_record',
    'edit_zahlungsanweisung_records',
    'edit_published_zahlungsanweisung_records',
]);
fsfp_cli_sync_caps('asta_finance_admin', array_values(array_unique(array_merge(
    ['read', 'upload_files'],
    $workflow_caps
))));

$portal_nav = fsfp_cli_portal_nav();
$dashboard_content = $portal_nav . '<!-- wp:heading --><h2>Dashboard</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Willkommen im Fachschafts-Finanzportal.</p><!-- /wp:paragraph -->
<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><!-- wp:heading {"level":3} --><h3>Beschlüsse</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Beschlüsse vorbereiten, einreichen und den Status nachverfolgen.</p><!-- /wp:paragraph --><!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/dashboard/beschluesse/">Beschlüsse öffnen</a></div><!-- /wp:button --></div><!-- /wp:buttons --></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><!-- wp:heading {"level":3} --><h3>Zahlungsanweisungen</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Zahlungsanweisungen erfassen und mit Beschlüssen verknüpfen.</p><!-- /wp:paragraph --><!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/dashboard/zahlungsanweisungen/">Zahlungsanweisungen öffnen</a></div><!-- /wp:button --></div><!-- /wp:buttons --></div><!-- /wp:column --></div><!-- /wp:columns -->';
$dashboard_id = fsfp_cli_upsert_page('dashboard', 'Dashboard', $dashboard_content);

fsfp_cli_upsert_page('beschluesse', 'Beschlüsse', $portal_nav . '<!-- wp:heading --><h2>Beschlüsse</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Frontend-Übersicht für Beschlüsse. Die Datenerfassung erfolgt in dieser Low-Code-Stufe über konfigurierte WordPress-Inhaltstypen; Backend-Zugriff für normale Benutzer ist gesperrt.</p><!-- /wp:paragraph -->
<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/dashboard/beschluss-erstellen/">Beschluss erstellen</a></div><!-- /wp:button --></div><!-- /wp:buttons -->', $dashboard_id);
fsfp_cli_upsert_page('beschluss-erstellen', 'Beschluss erstellen', $portal_nav . '<!-- wp:heading --><h2>Beschluss erstellen</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Frontend-Einstieg für neue Beschlüsse. Ein Formular-Plugin kann hier später eingebunden werden, ohne WordPress-Backendzugriff für normale Benutzer zu öffnen.</p><!-- /wp:paragraph -->', $dashboard_id);
fsfp_cli_upsert_page('zahlungsanweisungen', 'Zahlungsanweisungen', $portal_nav . '<!-- wp:heading --><h2>Zahlungsanweisungen</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Frontend-Übersicht für Zahlungsanweisungen.</p><!-- /wp:paragraph -->
<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/dashboard/zahlungsanweisung-erstellen/">Zahlungsanweisung erstellen</a></div><!-- /wp:button --></div><!-- /wp:buttons -->', $dashboard_id);
fsfp_cli_upsert_page('zahlungsanweisung-erstellen', 'Zahlungsanweisung erstellen', $portal_nav . '<!-- wp:heading --><h2>Zahlungsanweisung erstellen</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Frontend-Einstieg für neue Zahlungsanweisungen. Ein Formular-Plugin kann hier später eingebunden werden.</p><!-- /wp:paragraph -->', $dashboard_id);

fsfp_cli_ensure_menu('Portal Navigation', [
    ['title' => 'Dashboard', 'url' => home_url('/dashboard/')],
    ['title' => 'Beschlüsse', 'url' => home_url('/dashboard/beschluesse/')],
    ['title' => 'Zahlungsanweisungen', 'url' => home_url('/dashboard/zahlungsanweisungen/')],
    ['title' => 'Logout', 'url' => home_url('/wp-login.php?action=logout')],
]);
fsfp_cli_ensure_block_navigation('Portal Navigation');

update_option('rda_access_switch', 'manage_options');
update_option('rda_access_cap', 'manage_options');
update_option('rda_enable_profile', 0);
update_option('rda_redirect_url', home_url('/dashboard/'));
update_option('rda_login_message', '');
update_option('hab_settings', [
    'hab_disableforall' => 'no',
    'hab_userRoles' => [
        'asta_finance',
        'asta_finance_admin',
        'asta_reviewer',
        'auditor',
        'fachschaft_finance',
        'fachschaft_reader',
        'fsr_board',
        'fsr_member',
        'fsr_treasurer',
        'subscriber',
    ],
    'hab_capabilities' => '',
    'hab_disableforallGuests' => 'no',
]);

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
