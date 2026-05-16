<?php
/**
 * Idempotently creates roles, portal pages, demo users, Fachschaften, and demo
 * data for the configured-plugin prototype.
 *
 * This script runs through WP-CLI during setup only. No project-specific PHP is
 * loaded by WordPress during normal requests.
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

function fsfp_cli_workflow_types(string $slug): array
{
    return [
        'beschluss' => "b_{$slug}",
        'zahlung' => "za_{$slug}",
    ];
}

function fsfp_cli_capability_type(string $post_type): string
{
    return "{$post_type}_record";
}

function fsfp_cli_global_access_roles(): array
{
    return ['administrator', 'portal_admin', 'asta_finance', 'asta_reviewer', 'auditor'];
}

function fsfp_cli_global_edit_roles(): array
{
    return ['administrator', 'portal_admin', 'asta_finance', 'asta_reviewer'];
}

function fsfp_cli_fachschaft_access_roles(string $slug): array
{
    return array_merge([
        "fs_{$slug}_reader",
        "fs_{$slug}_finance",
    ], fsfp_cli_global_access_roles());
}

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

    fsfp_cli_add_caps($role, array_values(array_unique($caps)));
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

function fsfp_cli_read_caps(string $capability_type): array
{
    $plural = "{$capability_type}s";

    return [
        'read',
        "read_{$capability_type}",
        "read_private_{$plural}",
    ];
}

function fsfp_cli_edit_caps(string $capability_type): array
{
    $plural = "{$capability_type}s";

    return array_merge(fsfp_cli_read_caps($capability_type), [
        "edit_{$capability_type}",
        "edit_{$plural}",
        "edit_others_{$plural}",
        "edit_private_{$plural}",
        "edit_published_{$plural}",
        "publish_{$plural}",
    ]);
}

function fsfp_cli_admin_edit_access_cap(): string
{
    return 'fsfp_use_wp_admin';
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

function fsfp_cli_publish_existing_workflow_posts(array $fachschaften): void
{
    foreach ($fachschaften as $fachschaft) {
        $slug = sanitize_key($fachschaft['slug']);
        foreach (fsfp_cli_workflow_types($slug) as $post_type) {
            $post_ids = get_posts([
                'post_type' => $post_type,
                'post_status' => ['draft', 'pending'],
                'fields' => 'ids',
                'posts_per_page' => -1,
            ]);

            foreach ($post_ids as $post_id) {
                $updated = wp_update_post([
                    'ID' => (int) $post_id,
                    'post_status' => 'publish',
                ], true);

                if (is_wp_error($updated)) {
                    WP_CLI::error($updated->get_error_message());
                }
            }
        }
    }
}

function fsfp_cli_upsert_page(string $slug, string $title, string $content, int $parent_id = 0): int
{
    $existing_path = $slug;

    if ($parent_id > 0) {
        $parent = get_post($parent_id);
        if ($parent) {
            $existing_path = trim($parent->post_name . '/' . $slug, '/');
        }
    }

    $existing = get_page_by_path($existing_path, OBJECT, 'page');
    if ($existing) {
        $post_id = wp_update_post([
            'ID' => $existing->ID,
            'post_title' => $title,
            'post_name' => $slug,
            'post_content' => $content,
            'post_parent' => $parent_id,
            'post_status' => 'publish',
        ], true);

        if (is_wp_error($post_id)) {
            WP_CLI::error($post_id->get_error_message());
        }

        return (int) $post_id;
    }

    return fsfp_cli_upsert_post('page', $slug, $title, [
        'post_content' => $content,
        'post_parent' => $parent_id,
    ]);
}

function fsfp_cli_upsert_pods_template(string $slug, string $title, string $content): int
{
    if (!function_exists('pods_api')) {
        WP_CLI::error('Pods API is unavailable while saving templates.');
    }

    $api = pods_api();
    $existing = $api->load_template(['slug' => $slug]);
    if (!$existing) {
        $existing = $api->load_template(['title' => $title]);
    }

    $params = [
        'name' => $title,
        'code' => $content,
        'status' => 'publish',
    ];
    if (!empty($existing['id'])) {
        $params['id'] = $existing['id'];
    }

    $template_id = $api->save_template($params);
    if (is_wp_error($template_id)) {
        WP_CLI::error($template_id->get_error_message());
    }
    if (!$template_id) {
        WP_CLI::error("Could not save Pods template {$slug}.");
    }

    return (int) $template_id;
}

function fsfp_cli_delete_child_pages(int $parent_id): void
{
    $children = get_posts([
        'post_type' => 'page',
        'post_status' => 'any',
        'post_parent' => $parent_id,
        'fields' => 'ids',
        'posts_per_page' => -1,
    ]);

    foreach ($children as $child_id) {
        fsfp_cli_delete_child_pages((int) $child_id);
        wp_delete_post((int) $child_id, true);
    }
}

function fsfp_cli_restrict_page_to_roles(int $post_id, array $roles): void
{
    delete_post_meta($post_id, '_members_access_role');

    foreach (array_values(array_unique($roles)) as $role) {
        add_post_meta($post_id, '_members_access_role', $role, false);
    }

    update_post_meta($post_id, '_members_access_error', 'Sie haben keinen Zugriff auf diese Fachschaftsseite.');
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

function fsfp_cli_ensure_block_navigation(string $title, array $items): void
{
    if (!post_type_exists('wp_navigation')) {
        return;
    }

    $content = '';
    foreach ($items as $item) {
        $content .= sprintf(
            '<!-- wp:navigation-link {"label":"%s","url":"%s"} /-->',
            esc_html($item['title']),
            esc_url($item['url'])
        );
    }

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
        update_user_meta($user->ID, 'fsfp_fachschaft', $fachschaft);
    } else {
        delete_user_meta($user->ID, 'fsfp_fachschaft');
    }

    return (int) $user->ID;
}

function fsfp_cli_list_shortcode(string $post_type, string $kind, string $fachschaft_slug, bool $include_edit_link = false, bool $hide_drafts = false): string
{
    $date_th = $kind === 'beschluss' ? '<th>Datum</th>' : '';
    $date_td = $kind === 'beschluss' ? '<td>{@beschlussdatum}</td>' : '';
    $status_field = $kind === 'beschluss' ? 'beschluss_status' : 'zahlungs_status';
    $detail_slug = $kind === 'beschluss' ? 'beschluss-details' : 'zahlungsanweisung-details';
    $edit_slug = $kind === 'beschluss' ? 'beschluss-bearbeiten' : 'zahlungsanweisung-bearbeiten';
    $base_url = '/dashboard/' . esc_attr($fachschaft_slug) . '/';

    $actions = '<a href="' . $base_url . $detail_slug . '/?id={@ID}">Details</a>';
    if ($include_edit_link) {
        $actions .= ' | <a href="' . $base_url . $edit_slug . '/?id={@ID}">Bearbeiten</a>';
    }

    $row = '<tr>'
        . '<td>{@ID}</td>'
        . '<td>{@post_title}</td>'
        . '<td>{@' . $status_field . '}</td>'
        . $date_td
        . '<td>{@betrag}</td>'
        . '<td>' . $actions . '</td>'
        . '</tr>' . "\n";

    if ($hide_drafts) {
        $row = '[if field="' . $status_field . '" value="draft" compare="NOT IN"]' . $row . '[/if]' . "\n";
    }

    $template_slug = sanitize_key(sprintf(
        'fsfp-%s-%s-%s',
        $post_type,
        $include_edit_link ? 'edit' : 'view',
        $hide_drafts ? 'no-drafts' : 'all'
    ));
    fsfp_cli_upsert_pods_template(
        $template_slug,
        $template_slug,
        '[before]' . "\n"
        . '<table class="fsfp-table">' . "\n"
        . '<thead><tr>'
        . '<th>ID</th><th>Titel</th><th>Status</th>' . $date_th . '<th>Betrag</th><th>Aktionen</th>'
        . '</tr></thead>' . "\n"
        . '<tbody>' . "\n"
        . '[/before]' . "\n"
        . $row
        . '[after]' . "\n"
        . '</tbody></table>' . "\n"
        . '[/after]'
    );

    return '<!-- wp:group {"align":"wide"} --><div class="wp-block-group alignwide">' . "\n"
        . '<!-- wp:shortcode -->' . "\n"
        . '[pods name="' . esc_attr($post_type) . '" template="' . esc_attr($template_slug) . '" expires="-1" limit="10" search="1" filters="' . esc_attr($status_field) . '" filters_label="Filtern" pagination="1" pagination_type="paginate" shortcodes="1"]' . "\n"
        . '<!-- /wp:shortcode -->' . "\n"
        . '</div><!-- /wp:group -->';
}

function fsfp_cli_detail_page_content(string $post_type, string $kind, string $list_url): string
{
    $date_markup = $kind === 'beschluss' ? '<dt>Datum</dt><dd>{@beschlussdatum}</dd>' : '';
    $status_field = $kind === 'beschluss' ? 'beschluss_status' : 'zahlungs_status';
    $description_field = $kind === 'beschluss' ? 'zweck_beschreibung' : 'verwendungszweck';
    $reference_field = $kind === 'beschluss' ? 'zahlungsanweisung_ref' : 'beschluss_ref';

    return '<!-- wp:group --><div class="wp-block-group fsfp-detail-page">' . "\n"
        . '<!-- wp:html -->' . "\n"
        . '[pods name="' . esc_attr($post_type) . '" slug="{@get.id}"]' . "\n"
        . '<article class="fsfp-entry">'
        . '<h3>{@post_title}</h3>'
        . '<dl>'
        . '<dt>Interne ID</dt><dd>{@ID}</dd>'
        . '<dt>Status</dt><dd>{@' . $status_field . '}</dd>'
        . $date_markup
        . '<dt>Betrag</dt><dd>{@betrag}</dd>'
        . '<dt>Beschreibung</dt><dd>{@' . $description_field . '}</dd>'
        . '<dt>Referenz</dt><dd>{@' . $reference_field . '}</dd>'
        . '<dt>Notizen</dt><dd>{@notes}</dd>'
        . '</dl>'
        . '</article>' . "\n"
        . '[/pods]' . "\n"
        . '<!-- /wp:html -->' . "\n"
        . '<!-- wp:paragraph --><p><a class="wp-block-button__link wp-element-button" href="' . esc_url($list_url) . '">Zur Liste zurück</a></p><!-- /wp:paragraph -->' . "\n"
        . '</div><!-- /wp:group -->';
}

function fsfp_cli_list_intro(string $kind): string
{
    if ($kind === 'beschluss') {
        return '<!-- wp:group --><div class="wp-block-group fsfp-list-intro"><!-- wp:paragraph --><p>Beschlüsse suchen, nach Status filtern und direkt in Details oder Bearbeitung öffnen.</p><!-- /wp:paragraph --></div><!-- /wp:group -->';
    }

    return '<!-- wp:group --><div class="wp-block-group fsfp-list-intro"><!-- wp:paragraph --><p>Zahlungsanweisungen suchen, nach Status filtern und direkt in Details oder Bearbeitung öffnen.</p><!-- /wp:paragraph --></div><!-- /wp:group -->';
}

function fsfp_cli_members_access_block(array $roles, string $content): string
{
    return '<!-- wp:html -->' . "\n"
        . '[members_access role="' . esc_attr(implode(',', array_values(array_unique($roles)))) . '"]' . "\n"
        . $content . "\n"
        . '[/members_access]' . "\n"
        . '<!-- /wp:html -->';
}

function fsfp_cli_dashboard_card(string $title, string $url, string $button_label): string
{
    return '<!-- wp:group --><div class="wp-block-group"><!-- wp:heading {"level":3} --><h3>' . esc_html($title) . '</h3><!-- /wp:heading --><!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url($url) . '">' . esc_html($button_label) . '</a></div><!-- /wp:button --></div><!-- /wp:buttons --></div><!-- /wp:group -->';
}

function fsfp_cli_form_shortcode(string $post_type, string $fields, string $redirect): string
{
    return '<!-- wp:html -->' . "\n"
        . '[pods name="' . esc_attr($post_type) . '" form="true" fields="' . esc_attr($fields) . '" thank_you="' . esc_url($redirect) . '" label="Speichern"]' . "\n"
        . '<!-- /wp:html -->';
}

function fsfp_cli_edit_form_page(string $post_type, string $fields, string $list_url): string
{
    return '<!-- wp:group --><div class="wp-block-group fsfp-edit-page"><style>.fsfp-edit-page__form[hidden]{display:none}</style><!-- wp:paragraph --><p>Öffne einen Datensatz über den Bearbeiten-Link in der Liste. Diese Seite lädt einen vorhandenen Eintrag, wenn sie mit <code>?id=123</code> aufgerufen wird.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p><a class="wp-block-button__link wp-element-button" href="' . esc_url($list_url) . '">Zur Liste zurück</a></p><!-- /wp:paragraph --><div class="fsfp-edit-page__notice"><!-- wp:paragraph --><p>Kein Datensatz ausgewählt. Bitte nutze den Bearbeiten-Link in der Liste.</p><!-- /wp:paragraph --></div><div class="fsfp-edit-page__form" hidden>'
        . '[pods name="' . esc_attr($post_type) . '" form="true" slug="{@get.id}" fields="' . esc_attr($fields) . '" thank_you="' . esc_url($list_url) . '" label="Änderungen speichern"]'
        . '</div><script>(function(){var params=new URLSearchParams(window.location.search);var id=params.get("id");var form=document.querySelector(".fsfp-edit-page__form");var notice=document.querySelector(".fsfp-edit-page__notice");if(!form||!notice){return;}if(id&&id.length){form.hidden=false;notice.hidden=true;}else{form.hidden=true;notice.hidden=false;}})();</script></div><!-- /wp:group -->';
}

function fsfp_cli_workflow_action_buttons(string $create_url, array $roles): string
{
    return fsfp_cli_members_access_block(
        $roles,
        '<!-- wp:buttons --><div class="wp-block-buttons">'
        . '<!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url($create_url) . '">Neu erstellen</a></div><!-- /wp:button -->'
        . '</div><!-- /wp:buttons -->'
    );
}

$fachschaften = fsfp_cli_load_fachschaften();
$workflow_caps = [];
$read_workflow_caps = [];
$edit_workflow_caps = [];

fsfp_cli_role('portal_admin', 'Portal Admin', 'administrator');
fsfp_cli_role('asta_finance', 'AStA Finance');
fsfp_cli_role('asta_reviewer', 'AStA Reviewer');
fsfp_cli_role('auditor', 'Auditor');
fsfp_cli_role('fs_portal_empty', 'Portal ohne Fachschaft');

foreach ($fachschaften as $fachschaft) {
    $slug = sanitize_key($fachschaft['slug']);
    $short = $fachschaft['short_label'] ?? ucfirst($slug);
    fsfp_cli_role("fs_{$slug}_reader", "FS {$short} Reader");
    fsfp_cli_role("fs_{$slug}_finance", "FS {$short} Finance");

    foreach (fsfp_cli_workflow_types($slug) as $post_type) {
        $capability_type = fsfp_cli_capability_type($post_type);
        $workflow_caps = array_merge($workflow_caps, fsfp_cli_post_type_caps($capability_type));
        $read_workflow_caps = array_merge($read_workflow_caps, fsfp_cli_read_caps($capability_type));
        $edit_workflow_caps = array_merge($edit_workflow_caps, fsfp_cli_edit_caps($capability_type));
    }
}

$fachschaft_caps = fsfp_cli_post_type_caps('fachschaft_record');
$administrator_caps = get_role('administrator') ? array_keys(get_role('administrator')->capabilities) : ['read', 'manage_options'];
fsfp_cli_add_caps('administrator', [fsfp_cli_admin_edit_access_cap()]);

fsfp_cli_sync_caps('portal_admin', array_merge($administrator_caps, [fsfp_cli_admin_edit_access_cap()], $fachschaft_caps, $workflow_caps));
fsfp_cli_sync_caps('asta_finance', array_merge(['read', 'upload_files', fsfp_cli_admin_edit_access_cap()], $edit_workflow_caps));
fsfp_cli_sync_caps('asta_reviewer', array_merge(['read', fsfp_cli_admin_edit_access_cap()], $edit_workflow_caps));
fsfp_cli_sync_caps('auditor', array_merge(['read'], $read_workflow_caps));
fsfp_cli_sync_caps('fs_portal_empty', ['read']);

foreach ($fachschaften as $fachschaft) {
    $slug = sanitize_key($fachschaft['slug']);
    $reader_caps = ['read'];
    $finance_caps = ['read', 'upload_files'];

    foreach (fsfp_cli_workflow_types($slug) as $post_type) {
        $capability_type = fsfp_cli_capability_type($post_type);
        $reader_caps = array_merge($reader_caps, fsfp_cli_read_caps($capability_type));
        $finance_caps = array_merge($finance_caps, fsfp_cli_edit_caps($capability_type));
    }

    fsfp_cli_sync_caps("fs_{$slug}_reader", $reader_caps);
    fsfp_cli_sync_caps("fs_{$slug}_finance", array_merge($finance_caps, [fsfp_cli_admin_edit_access_cap()]));
}

$members_settings = get_option('members_settings', []);
if (!is_array($members_settings)) {
    $members_settings = [];
}
$members_settings['content_permissions'] = 1;
$members_settings['hide_posts_rest_api'] = 1;
$members_settings['content_permissions_error'] = 'Sie haben keinen Zugriff auf diese Fachschaftsseite.';
update_option('members_settings', $members_settings);

$dashboard_blocks = '';
$menu_items = [
    ['title' => 'Dashboard', 'url' => home_url('/dashboard/')],
    ['title' => 'Logout', 'url' => home_url('/wp-login.php?action=logout')],
];

foreach ($fachschaften as $fachschaft) {
    $slug = sanitize_key($fachschaft['slug']);
    $label = $fachschaft['label'];
    $dashboard_blocks .= fsfp_cli_members_access_block(
        fsfp_cli_fachschaft_access_roles($slug),
        fsfp_cli_dashboard_card($label, home_url("/dashboard/{$slug}/"), 'Öffnen')
    );
}

$dashboard_blocks .= fsfp_cli_members_access_block(
    fsfp_cli_global_access_roles(),
    fsfp_cli_dashboard_card('AStA / Gesamtübersicht', home_url('/dashboard/beschluesse/'), 'Alle Beschlüsse öffnen')
);
$dashboard_blocks .= fsfp_cli_members_access_block(
    ['fs_portal_empty'],
    '<!-- wp:paragraph --><p>Ihr Konto ist keiner Fachschaft zugeordnet. Bitte wenden Sie sich an die Portal-Administration.</p><!-- /wp:paragraph -->'
);

$dashboard_content = '<!-- wp:heading --><h2>Fachschafts-Finanzportal</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Beschlüsse, Belege und Zahlungsanweisungen werden nach Fachschaft getrennt verwaltet.</p><!-- /wp:paragraph -->
'
    . $dashboard_blocks;
$dashboard_id = fsfp_cli_upsert_page('dashboard', 'Dashboard', $dashboard_content);
fsfp_cli_delete_child_pages($dashboard_id);

update_option('show_on_front', 'page');
update_option('page_on_front', $dashboard_id);
update_option('page_for_posts', 0);

$global_beschluesse = '<!-- wp:heading --><h2>Alle Beschlüsse</h2><!-- /wp:heading -->';
$global_zahlungen = '<!-- wp:heading --><h2>Alle Zahlungsanweisungen</h2><!-- /wp:heading -->';

foreach ($fachschaften as $fachschaft) {
    $slug = sanitize_key($fachschaft['slug']);
    $label = $fachschaft['label'];
    $types = fsfp_cli_workflow_types($slug);
    $fachschaft_view_roles = fsfp_cli_fachschaft_access_roles($slug);
    $edit_roles = ["fs_{$slug}_finance", ...fsfp_cli_global_edit_roles()];
    $view_only_roles = array_values(array_diff($fachschaft_view_roles, $edit_roles));

    $fachschaft_id = fsfp_cli_upsert_page($slug, $label, '<!-- wp:heading --><h2>' . esc_html($label) . '</h2><!-- /wp:heading -->
<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/dashboard/' . esc_attr($slug) . '/beschluesse/">Beschlüsse</a></div><!-- /wp:button --><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/dashboard/' . esc_attr($slug) . '/zahlungsanweisungen/">Zahlungsanweisungen</a></div><!-- /wp:button --></div><!-- /wp:buttons -->', $dashboard_id);
    fsfp_cli_restrict_page_to_roles($fachschaft_id, fsfp_cli_fachschaft_access_roles($slug));

    $beschluss_list = '<!-- wp:heading --><h2>Beschlüsse</h2><!-- /wp:heading -->'
        . fsfp_cli_list_intro('beschluss')
        . fsfp_cli_members_access_block($view_only_roles, fsfp_cli_list_shortcode($types['beschluss'], 'beschluss', $slug, false, true))
        . fsfp_cli_workflow_action_buttons(
            home_url("/dashboard/{$slug}/beschluss-erstellen/"),
            $edit_roles
        )
        . fsfp_cli_members_access_block($edit_roles, fsfp_cli_list_shortcode($types['beschluss'], 'beschluss', $slug, true, false));
    $beschluss_list_id = fsfp_cli_upsert_page('beschluesse', 'Beschlüsse', $beschluss_list, $fachschaft_id);
    fsfp_cli_restrict_page_to_roles($beschluss_list_id, $fachschaft_view_roles);

    $beschluss_detail_id = fsfp_cli_upsert_page('beschluss-details', 'Beschluss Details', fsfp_cli_detail_page_content($types['beschluss'], 'beschluss', home_url("/dashboard/{$slug}/beschluesse/")), $fachschaft_id);
    fsfp_cli_restrict_page_to_roles($beschluss_detail_id, $fachschaft_view_roles);

    $beschluss_form_fields = 'post_title,beschlussdatum,betrag,zweck_beschreibung,beschluss_status,belege,zahlungsanweisung_ref,notes';
    $beschluss_create_id = fsfp_cli_upsert_page('beschluss-erstellen', 'Beschluss erstellen', fsfp_cli_form_shortcode($types['beschluss'], $beschluss_form_fields, home_url("/dashboard/{$slug}/beschluesse/?created=1")), $fachschaft_id);
    fsfp_cli_restrict_page_to_roles($beschluss_create_id, $edit_roles);
    $beschluss_edit_id = fsfp_cli_upsert_page('beschluss-bearbeiten', 'Beschluss bearbeiten', fsfp_cli_edit_form_page($types['beschluss'], $beschluss_form_fields, home_url("/dashboard/{$slug}/beschluesse/")), $fachschaft_id);
    fsfp_cli_restrict_page_to_roles($beschluss_edit_id, $edit_roles);

    $zahlung_list = '<!-- wp:heading --><h2>Zahlungsanweisungen</h2><!-- /wp:heading -->'
        . fsfp_cli_list_intro('zahlung')
        . fsfp_cli_members_access_block($view_only_roles, fsfp_cli_list_shortcode($types['zahlung'], 'zahlung', $slug, false, true))
        . fsfp_cli_workflow_action_buttons(
            home_url("/dashboard/{$slug}/zahlungsanweisung-erstellen/"),
            $edit_roles
        )
        . fsfp_cli_members_access_block($edit_roles, fsfp_cli_list_shortcode($types['zahlung'], 'zahlung', $slug, true, false));
    $zahlung_list_id = fsfp_cli_upsert_page('zahlungsanweisungen', 'Zahlungsanweisungen', $zahlung_list, $fachschaft_id);
    fsfp_cli_restrict_page_to_roles($zahlung_list_id, $fachschaft_view_roles);

    $zahlung_detail_id = fsfp_cli_upsert_page('zahlungsanweisung-details', 'Zahlungsanweisung Details', fsfp_cli_detail_page_content($types['zahlung'], 'zahlung', home_url("/dashboard/{$slug}/zahlungsanweisungen/")), $fachschaft_id);
    fsfp_cli_restrict_page_to_roles($zahlung_detail_id, $fachschaft_view_roles);

    $zahlung_form_fields = 'post_title,betrag,verwendungszweck,zahlungs_status,belege,beschluss_ref,notes';
    $zahlung_create_id = fsfp_cli_upsert_page('zahlungsanweisung-erstellen', 'Zahlungsanweisung erstellen', fsfp_cli_form_shortcode($types['zahlung'], $zahlung_form_fields, home_url("/dashboard/{$slug}/zahlungsanweisungen/?created=1")), $fachschaft_id);
    fsfp_cli_restrict_page_to_roles($zahlung_create_id, $edit_roles);
    $zahlung_edit_id = fsfp_cli_upsert_page('zahlungsanweisung-bearbeiten', 'Zahlungsanweisung bearbeiten', fsfp_cli_edit_form_page($types['zahlung'], $zahlung_form_fields, home_url("/dashboard/{$slug}/zahlungsanweisungen/")), $fachschaft_id);
    fsfp_cli_restrict_page_to_roles($zahlung_edit_id, $edit_roles);

    $global_beschluesse .= '<!-- wp:heading {"level":3} --><h3>' . esc_html($label) . '</h3><!-- /wp:heading -->' . fsfp_cli_list_intro('beschluss') . fsfp_cli_list_shortcode($types['beschluss'], 'beschluss', $slug);
    $global_zahlungen .= '<!-- wp:heading {"level":3} --><h3>' . esc_html($label) . '</h3><!-- /wp:heading -->' . fsfp_cli_list_intro('zahlung') . fsfp_cli_list_shortcode($types['zahlung'], 'zahlung', $slug);
}

$global_beschluesse_id = fsfp_cli_upsert_page('beschluesse', 'Alle Beschlüsse', $global_beschluesse, $dashboard_id);
fsfp_cli_restrict_page_to_roles($global_beschluesse_id, fsfp_cli_global_access_roles());
$global_zahlungen_id = fsfp_cli_upsert_page('zahlungsanweisungen', 'Alle Zahlungsanweisungen', $global_zahlungen, $dashboard_id);
fsfp_cli_restrict_page_to_roles($global_zahlungen_id, fsfp_cli_global_access_roles());

fsfp_cli_ensure_menu('Portal Navigation', $menu_items);
fsfp_cli_ensure_block_navigation('Portal Navigation', $menu_items);

update_option('rda_access_switch', 'capability');
update_option('rda_access_cap', fsfp_cli_admin_edit_access_cap());
update_option('rda_enable_profile', 0);
update_option('rda_redirect_url', home_url('/dashboard/'));
update_option('rda_login_message', '');

$hidden_admin_bar_roles = [
    'asta_finance',
    'asta_reviewer',
    'auditor',
    'fs_portal_empty',
    'subscriber',
];
foreach ($fachschaften as $fachschaft) {
    $slug = sanitize_key($fachschaft['slug']);
    $hidden_admin_bar_roles[] = "fs_{$slug}_reader";
    $hidden_admin_bar_roles[] = "fs_{$slug}_finance";
}

update_option('hab_settings', [
    'hab_disableforall' => 'no',
    'hab_userRoles' => array_values(array_unique($hidden_admin_bar_roles)),
    'hab_capabilities' => '',
    'hab_disableforallGuests' => 'no',
]);

delete_option('fs_finanzportal_aam_policy_manifest');

// Add global styles for improved readability (Problem 1 & Problem 4 wide theme)
if (function_exists('wp_update_custom_css_post')) {
    $custom_css = "
body .is-layout-constrained > .alignwide,
body .is-layout-constrained > .wp-block-group.alignwide,
body .entry-content > .alignwide { max-width: min(1200px, calc(100vw - 3rem)); }
.fsfp-list-intro { margin: 0 0 0.75rem; padding: 0; color: #475569; }
.fsfp-list-intro p { margin: 0; }
.pods-form-filters { display: flex; flex-wrap: wrap; align-items: end; gap: 0.75rem; margin: 1rem 0; padding: 1rem; border: 1px solid #d8dee4; background: #f6f8fa; }
.pods-form-filters .pods-form-ui-field { min-width: 12rem; margin: 0; }
.pods-form-filters-search { min-width: 16rem; flex: 1 1 18rem; }
.pods-form-filters input,
.pods-form-filters select { min-height: 2.5rem; padding: 0.45rem 0.65rem; border: 1px solid #cbd5e1; border-radius: 4px; }
.pods-form-filters-submit { cursor: pointer; background: #1f6feb; color: #fff; border-color: #1f6feb; }
.fsfp-table { width: 100%; border-collapse: collapse; margin: 1rem 0 1.5rem; font-size: 0.95rem; table-layout: auto; }
.fsfp-table th, .fsfp-table td { padding: 0.85rem; border-bottom: 1px solid #d8dee4; text-align: left; vertical-align: top; }
.fsfp-table th { background-color: #f6f8fa; color: #24292f; font-weight: 600; }
.fsfp-table tbody tr:hover { background: #f6f8fa; }
.fsfp-table td:last-child { white-space: nowrap; }
.wp-block-buttons { margin-bottom: 1rem; }
.fsfp-detail-page dt { font-weight: bold; margin-top: 1rem; }
.fsfp-detail-page dd { margin-left: 0; margin-bottom: 0.5rem; }
footer, .site-footer, .wp-block-template-part.site-footer { display: none !important; }
";
    wp_update_custom_css_post($custom_css);
}

foreach ($fachschaften as $fachschaft) {
    fsfp_cli_upsert_post('fachschaft', sanitize_key($fachschaft['slug']), $fachschaft['label']);
}

$users = [
    ['demo-fachschaft', 'demo-fachschaft@example.com', 'fs_informatik_finance', 'informatik'],
    ['demo-informatik-reader', 'demo-informatik-reader@example.com', 'fs_informatik_reader', 'informatik'],
    ['demo-informatik-reader2', 'demo-informatik-reader2@example.com', 'fs_informatik_reader', 'informatik'],
    ['demo-maschinenbau-finance', 'demo-maschinenbau-finance@example.com', 'fs_maschinenbau_finance', 'maschinenbau'],
    ['demo-maschinenbau-reader', 'demo-maschinenbau-reader@example.com', 'fs_maschinenbau_reader', 'maschinenbau'],
    ['demo-maschinenbau-reader2', 'demo-maschinenbau-reader2@example.com', 'fs_maschinenbau_reader', 'maschinenbau'],
    ['demo-philosophie-finance', 'demo-philosophie-finance@example.com', 'fs_philosophie_finance', 'philosophie'],
    ['demo-philosophie', 'demo-philosophie@example.com', 'fs_philosophie_reader', 'philosophie'],
    ['demo-philosophie-reader2', 'demo-philosophie-reader2@example.com', 'fs_philosophie_reader', 'philosophie'],
    ['demo-asta', 'demo-asta@example.com', 'asta_finance', ''],
    ['demo-reviewer', 'demo-reviewer@example.com', 'asta_reviewer', ''],
    ['demo-auditor', 'demo-auditor@example.com', 'auditor', ''],
    ['demo-unassigned', 'demo-unassigned@example.com', 'fs_portal_empty', ''],
];

foreach ($users as $user) {
    fsfp_cli_upsert_user($user[0], $user[1], $user[2], $user[3]);
}

$demo_file = fsfp_cli_config_path('demo/beschluesse.json');
$items = json_decode(file_get_contents($demo_file), true);
if (!is_array($items)) {
    WP_CLI::error('Invalid demo Beschluesse JSON.');
}

foreach ($items as $item) {
    $fachschaft = sanitize_key($item['fachschaft'] ?? '');
    if ($fachschaft === '') {
        WP_CLI::error('Demo Beschluss is missing Fachschaft.');
    }

    $post_type = fsfp_cli_workflow_types($fachschaft)['beschluss'];
    $author = get_user_by('login', $item['author'] ?? 'demo-fachschaft');
    $post_id = fsfp_cli_upsert_post($post_type, $item['slug'], $item['title'], [
        'post_author' => $author ? $author->ID : 1,
        'post_status' => 'publish',
    ]);

    foreach (['fachschaft', 'beschlussdatum', 'betrag', 'zweck_beschreibung', 'zahlungsanweisung_ref', 'notes'] as $field) {
        update_post_meta($post_id, $field, $item[$field] ?? '');
    }

    update_post_meta($post_id, 'beschluss_status', $item['status'] ?? 'draft');
}

fsfp_cli_publish_existing_workflow_posts($fachschaften);

flush_rewrite_rules();

WP_CLI::success('Portal content configured.');
