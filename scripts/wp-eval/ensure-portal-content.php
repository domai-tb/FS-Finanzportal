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

function fsfp_cli_global_overview_roles(): array
{
    return ['administrator', 'portal_admin', 'asta_finance', 'asta_reviewer'];
}

function fsfp_cli_global_edit_roles(): array
{
    return fsfp_cli_global_zahlung_edit_roles();
}

function fsfp_cli_global_beschluss_edit_roles(): array
{
    return ['administrator', 'portal_admin'];
}

function fsfp_cli_global_zahlung_edit_roles(): array
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

function fsfp_cli_fachschaft_view_roles(string $slug): array
{
    return [
        "fs_{$slug}_reader",
        "fs_{$slug}_finance",
        'administrator',
        'portal_admin',
        'auditor',
    ];
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

function fsfp_cli_normalize_workflow_statuses(array $fachschaften): void
{
    $beschluss_valid = ['draft', 'approved', 'rejected'];
    $zahlung_valid = ['draft', 'submitted', 'correction_requested', 'cancelled', 'executed'];
    $zahlung_legacy_map = [
        'approved' => 'executed',
        'rejected' => 'correction_requested',
        'archived' => 'cancelled',
    ];

    foreach ($fachschaften as $fachschaft) {
        $slug = sanitize_key($fachschaft['slug']);
        $types = fsfp_cli_workflow_types($slug);

        foreach ([
            ['post_type' => $types['beschluss'], 'field' => 'beschluss_status', 'valid' => $beschluss_valid, 'map' => []],
            ['post_type' => $types['zahlung'], 'field' => 'zahlungs_status', 'valid' => $zahlung_valid, 'map' => $zahlung_legacy_map],
        ] as $workflow) {
            $post_ids = get_posts([
                'post_type' => $workflow['post_type'],
                'post_status' => 'any',
                'fields' => 'ids',
                'posts_per_page' => -1,
            ]);

            foreach ($post_ids as $post_id) {
                $status = (string) get_post_meta((int) $post_id, $workflow['field'], true);
                if (isset($workflow['map'][$status])) {
                    update_post_meta((int) $post_id, $workflow['field'], $workflow['map'][$status]);
                } elseif (!in_array($status, $workflow['valid'], true)) {
                    update_post_meta((int) $post_id, $workflow['field'], 'draft');
                }
            }
        }
    }
}

function fsfp_cli_configure_meta_ledger(array $fachschaften): void
{
    $tracked_post_types = [];

    foreach ($fachschaften as $fachschaft) {
        $tracked_post_types = array_merge($tracked_post_types, array_values(fsfp_cli_workflow_types(sanitize_key($fachschaft['slug']))));
    }

    update_option('meta_ledger_post_types', array_values(array_unique($tracked_post_types)));
    update_option('meta_ledger_retention_count', 200);
    update_option('meta_ledger_ignored_keys', implode("\n", [
        '_edit_lock',
        '_edit_last',
        '_wp_old_slug',
        '_wp_old_date',
        '_encloseme',
        '_pingme',
        '_members_access_role',
        '_members_access_error',
    ]));
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

function fsfp_cli_navigation_link_block(array $item): string
{
    $attrs = [
        'label' => $item['title'],
        'url' => $item['url'],
    ];

    if (!empty($item['className'])) {
        $attrs['className'] = $item['className'];
    }

    return '<!-- wp:navigation-link ' . wp_json_encode($attrs) . ' /-->';
}

function fsfp_cli_navigation_target_script(array $fachschaften): string
{
    $slugs = array_map(
        fn($fachschaft) => sanitize_key($fachschaft['slug']),
        $fachschaften
    );

    return '<script>(function(){'
        . 'var slugs=' . wp_json_encode(array_values($slugs)) . ';'
        . 'function ready(fn){if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",fn);}else{fn();}}'
        . 'function item(selector){var link=document.querySelector(selector+" a, a"+selector);return {link:link,wrap:link&&(link.closest(".fsfp-nav-workflow")||link)};}'
        . 'function pathParts(path){return path.split("/").filter(Boolean);}'
        . 'function scopedBaseFromPath(){var parts=pathParts(window.location.pathname);var slug=parts[0]==="dashboard"?parts[1]:"";return slugs.indexOf(slug)!==-1?"/dashboard/"+slug+"/":"";}'
        . 'function isGlobalWorkflowPath(){var parts=pathParts(window.location.pathname);return parts[0]==="dashboard"&&(parts[1]==="beschluesse"||parts[1]==="zahlungsanweisungen");}'
        . 'function dashboardBaseFromContent(){var root=document.querySelector("main")||document.querySelector(".entry-content")||document.body;if(!root){return "";}if(root.querySelector("a[href*=\'/dashboard/beschluesse/\']")){return "/dashboard/";}var found=[];root.querySelectorAll("a[href*=\'/dashboard/\']").forEach(function(a){var parts=pathParts(new URL(a.href,window.location.origin).pathname);var slug=parts[0]==="dashboard"?parts[1]:"";if(slugs.indexOf(slug)!==-1&&found.indexOf(slug)===-1){found.push(slug);}});return found.length===1?"/dashboard/"+found[0]+"/":"";}'
        . 'function setVisible(entry,href){if(!entry.link||!entry.wrap){return;}entry.link.href=href;entry.wrap.classList.add("is-ready");entry.wrap.classList.remove("is-hidden");}'
        . 'function hide(entry){if(entry.wrap){entry.wrap.classList.add("is-hidden");entry.wrap.classList.remove("is-ready");}}'
        . 'ready(function(){var b=item(".fsfp-nav-beschluesse");var z=item(".fsfp-nav-zahlungsanweisungen");var base=scopedBaseFromPath();var parts=pathParts(window.location.pathname);if(!base&&isGlobalWorkflowPath()){base="/dashboard/";}if(!base&&parts.length===1&&parts[0]==="dashboard"){base=dashboardBaseFromContent();}if(!base){hide(b);hide(z);return;}setVisible(b,base+"beschluesse/");setVisible(z,base+"zahlungsanweisungen/");});'
        . '})();</script>';
}

function fsfp_cli_ensure_block_navigation(string $title, array $items): void
{
    if (!post_type_exists('wp_navigation')) {
        return;
    }

    $content = '';
    foreach ($items as $item) {
        if (($item['type'] ?? '') === 'html') {
            $content .= '<!-- wp:html -->' . ($item['content'] ?? '') . '<!-- /wp:html -->';
        } else {
            $content .= fsfp_cli_navigation_link_block($item);
        }
    }

    $existing = get_page_by_path(sanitize_title($title), OBJECT, 'wp_navigation');

    if ($existing) {
        wp_update_post([
            'ID' => $existing->ID,
            'post_title' => $title,
            'post_name' => sanitize_title($title),
            'post_content' => wp_slash($content),
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
            'post_content' => wp_slash($content),
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
        'post_content' => wp_slash($content),
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

function fsfp_cli_status_options(string $kind): array
{
    if ($kind === 'beschluss') {
        return [
            'Entwurf' => 'Entwurf',
            'Genehmigt' => 'Genehmigt',
            'Abgelehnt' => 'Abgelehnt',
        ];
    }

    return [
        'Entwurf' => 'Entwurf',
        'Eingereicht' => 'Eingereicht',
        'Rückfrage' => 'Rückfrage',
        'Stoniert' => 'Stoniert',
        'Ausgeführt' => 'Ausgeführt',
    ];
}

function fsfp_cli_status_select_options(string $kind): string
{
    $status_select = '<option value="">Alle Status</option>';
    foreach (fsfp_cli_status_options($kind) as $value => $label) {
        $status_select .= '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
    }

    return $status_select;
}

function fsfp_cli_return_to_url(string $url): string
{
    return rawurlencode($url);
}

function fsfp_cli_contextual_back_link_script(string $default_url): string
{
    return '<script>(function(){'
        . 'var link=document.querySelector("[data-fsfp-back-link]");'
        . 'if(!link){return;}'
        . 'var fallback="' . esc_js($default_url) . '";'
        . 'function pathParts(path){return (path||"").split("/").filter(Boolean);}'
        . 'function isListPath(path){var parts=pathParts(path);if(parts.length===2&&parts[0]==="dashboard"&&(parts[1]==="beschluesse"||parts[1]==="zahlungsanweisungen")){return true;}return parts.length===3&&parts[0]==="dashboard"&&(parts[2]==="beschluesse"||parts[2]==="zahlungsanweisungen");}'
        . 'function safeUrl(value){if(!value){return "";}try{var url=new URL(value,window.location.origin);if(url.origin!==window.location.origin||!isListPath(url.pathname)){return "";}return url.pathname+url.search+url.hash;}catch(e){return "";}}'
        . 'var params=new URLSearchParams(window.location.search);'
        . 'var target=safeUrl(params.get("return_to"))||safeUrl(document.referrer)||fallback;'
        . 'link.setAttribute("href",target);'
        . '})();</script>';
}

function fsfp_cli_list_shortcode(string $post_type, string $kind, string $fachschaft_slug, bool $include_edit_link = false, bool $hide_drafts = false, string $edit_label = 'Bearbeiten'): string
{
    $date_th = $kind === 'beschluss' ? '<th>Datum</th>' : '';
    $date_td = $kind === 'beschluss' ? '<td>{@beschlussdatum}</td>' : '';
    $status_field = $kind === 'beschluss' ? 'beschluss_status' : 'zahlungs_status';
    $detail_slug = $kind === 'beschluss' ? 'beschluss-details' : 'zahlungsanweisung-details';
    $edit_slug = $kind === 'beschluss' ? 'beschluss-bearbeiten' : 'zahlungsanweisung-bearbeiten';
    $base_url = '/dashboard/' . esc_attr($fachschaft_slug) . '/';
    $list_url = $base_url . ($kind === 'beschluss' ? 'beschluesse/' : 'zahlungsanweisungen/');
    $return_to = fsfp_cli_return_to_url($list_url);

    $actions = '<a href="' . $base_url . $detail_slug . '/?id={@ID}&return_to=' . esc_attr($return_to) . '">Details</a>';
    if ($include_edit_link) {
        $edit_action = ' | <a href="' . $base_url . $edit_slug . '/?id={@ID}&return_to=' . esc_attr($return_to) . '">' . esc_html($edit_label) . '</a>';
        if ($kind === 'zahlung') {
            $actions .= '[if field="zahlungs_status" value="executed" compare="NOT IN"]' . $edit_action . '[/if]';
        } else {
            $actions .= '[if field="beschluss_status" value="draft"]' . $edit_action . '[/if]';
        }
    }

    $row = '<tr data-status="{@' . $status_field . '}">'
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
        $include_edit_link ? sanitize_key($edit_label) : 'view',
        $hide_drafts ? 'no-drafts' : 'all'
    ));
    fsfp_cli_upsert_pods_template(
        $template_slug,
        $template_slug,
        $row
    );

    $table_id = sanitize_html_class($template_slug);
    $script = '<script>(function(){'
        . 'var root=document.getElementById("' . esc_js($table_id) . '");'
        . 'if(!root){return;}'
        . 'var tbody=root.querySelector("[data-scoped-body]");'
        . 'var search=root.querySelector("[data-scoped-search]");'
        . 'var status=root.querySelector("[data-scoped-status]");'
        . 'var prev=root.querySelector("[data-scoped-prev]");'
        . 'var next=root.querySelector("[data-scoped-next]");'
        . 'var pageLabel=root.querySelector("[data-scoped-page]");'
        . 'var empty=root.querySelector("[data-scoped-empty]");'
        . 'var pageSize=10;'
        . 'var page=1;'
        . 'var rows=Array.prototype.slice.call(tbody.querySelectorAll("tr")).map(function(row){return row.cloneNode(true);});'
        . 'function text(row){return row.textContent.toLowerCase();}'
        . 'function filtered(){var q=(search.value||"").toLowerCase();var s=status.value||"";return rows.filter(function(row){return (!q||text(row).indexOf(q)!==-1)&&(!s||row.getAttribute("data-status")===s);});}'
        . 'function render(){var items=filtered();var pages=Math.max(1,Math.ceil(items.length/pageSize));if(page>pages){page=pages;}tbody.innerHTML="";items.slice((page-1)*pageSize,page*pageSize).forEach(function(row){tbody.appendChild(row.cloneNode(true));});empty.hidden=items.length!==0;pageLabel.textContent=items.length?("Seite "+page+" von "+pages+" · "+items.length+" Einträge"):"Keine Einträge";prev.disabled=page<=1;next.disabled=page>=pages;}'
        . 'function reset(){page=1;render();}'
        . '[search,status].forEach(function(control){control.addEventListener("input",reset);control.addEventListener("change",reset);});'
        . 'prev.addEventListener("click",function(){if(page>1){page--;render();}});'
        . 'next.addEventListener("click",function(){page++;render();});'
        . 'render();'
        . '})();</script>';

    return '<!-- wp:group {"align":"wide"} --><div id="' . esc_attr($table_id) . '" class="wp-block-group alignwide fsfp-scoped-overview">' . "\n"
        . '<div class="fsfp-unified-controls">'
        . '<label>Suche <input type="search" data-scoped-search placeholder="Titel, ID, Betrag"></label>'
        . '<label>Status <select data-scoped-status>' . fsfp_cli_status_select_options($kind) . '</select></label>'
        . '</div>'
        . '<table class="fsfp-table fsfp-scoped-table"><thead><tr><th>ID</th><th>Titel</th><th>Status</th>' . $date_th . '<th>Betrag</th><th>Aktionen</th></tr></thead><tbody data-scoped-body>'
        . '<!-- wp:shortcode -->' . "\n"
        . '[pods name="' . esc_attr($post_type) . '" template="' . esc_attr($template_slug) . '" expires="-1" limit="-1" shortcodes="1"]' . "\n"
        . '<!-- /wp:shortcode -->' . "\n"
        . '</tbody></table>'
        . '<p class="fsfp-unified-empty" data-scoped-empty hidden>Keine passenden Einträge gefunden.</p>'
        . '<div class="fsfp-unified-pagination"><button type="button" data-scoped-prev>Zurück</button><span data-scoped-page></span><button type="button" data-scoped-next>Weiter</button></div>'
        . $script
        . '</div><!-- /wp:group -->';
}

function fsfp_cli_unified_overview_source(string $post_type, string $kind, string $fachschaft_slug, string $fachschaft_label): string
{
    $status_field = $kind === 'beschluss' ? 'beschluss_status' : 'zahlungs_status';
    $detail_slug = $kind === 'beschluss' ? 'beschluss-details' : 'zahlungsanweisung-details';
    $edit_slug = $kind === 'beschluss' ? '' : 'zahlungsanweisung-bearbeiten';
    $date_td = $kind === 'beschluss' ? '<td>{@beschlussdatum}</td>' : '';
    $base_url = '/dashboard/' . esc_attr($fachschaft_slug) . '/';
    $list_url = $kind === 'beschluss' ? '/dashboard/beschluesse/' : '/dashboard/zahlungsanweisungen/';
    $return_to = fsfp_cli_return_to_url($list_url);

    $actions = '<a href="' . $base_url . $detail_slug . '/?id={@ID}&return_to=' . esc_attr($return_to) . '">Details</a>';
    if ($kind === 'zahlung') {
        $review_action = ' | <a href="' . $base_url . $edit_slug . '/?id={@ID}&return_to=' . esc_attr($return_to) . '">Rückfrage / Ausgeführt</a>';
        $actions .= '[if field="zahlungs_status" value="executed" compare="NOT IN"]' . $review_action . '[/if]';
    }

    $row = '<tr data-fachschaft="' . esc_attr($fachschaft_slug) . '" data-fachschaft-label="' . esc_attr($fachschaft_label) . '" data-status="{@' . $status_field . '}">'
        . '<td>' . esc_html($fachschaft_label) . '</td>'
        . '<td>{@ID}</td>'
        . '<td>{@post_title}</td>'
        . '<td>{@' . $status_field . '}</td>'
        . $date_td
        . '<td>{@betrag}</td>'
        . '<td>' . $actions . '</td>'
        . '</tr>' . "\n";

    $template_slug = sanitize_key(sprintf('fsfp-global-%s-%s-rows', $post_type, $kind));
    fsfp_cli_upsert_pods_template($template_slug, $template_slug, $row);

    return '<!-- wp:shortcode -->' . "\n"
        . '[pods name="' . esc_attr($post_type) . '" template="' . esc_attr($template_slug) . '" expires="-1" limit="-1" shortcodes="1"]' . "\n"
        . '<!-- /wp:shortcode -->' . "\n";
}

function fsfp_cli_unified_overview_page(string $kind, array $fachschaften): string
{
    $is_beschluss = $kind === 'beschluss';
    $table_id = 'fsfp-unified-' . ($is_beschluss ? 'beschluesse' : 'zahlungen');
    $title = $is_beschluss ? 'Alle Beschlüsse' : 'Alle Zahlungsanweisungen';
    $date_th = $is_beschluss ? '<th>Datum</th>' : '';

    $fachschaft_select = '<option value="">Alle Fachschaften</option>';
    $sources = '';
    foreach ($fachschaften as $fachschaft) {
        $slug = sanitize_key($fachschaft['slug']);
        $label = $fachschaft['label'];
        $types = fsfp_cli_workflow_types($slug);
        $fachschaft_select .= '<option value="' . esc_attr($slug) . '">' . esc_html($label) . '</option>';
        $sources .= fsfp_cli_unified_overview_source($types[$kind === 'beschluss' ? 'beschluss' : 'zahlung'], $kind, $slug, $label);
    }

    $script = '<script>(function(){'
        . 'var root=document.getElementById("' . esc_js($table_id) . '");'
        . 'if(!root){return;}'
        . 'var tbody=root.querySelector("[data-unified-body]");'
        . 'var search=root.querySelector("[data-unified-search]");'
        . 'var status=root.querySelector("[data-unified-status]");'
        . 'var fachschaft=root.querySelector("[data-unified-fachschaft]");'
        . 'var prev=root.querySelector("[data-unified-prev]");'
        . 'var next=root.querySelector("[data-unified-next]");'
        . 'var pageLabel=root.querySelector("[data-unified-page]");'
        . 'var empty=root.querySelector("[data-unified-empty]");'
        . 'var pageSize=10;'
        . 'var page=1;'
        . 'var rows=Array.prototype.slice.call(tbody.querySelectorAll("tr")).map(function(row){return row.cloneNode(true);});'
        . 'function text(row){return row.textContent.toLowerCase();}'
        . 'function filtered(){var q=(search.value||"").toLowerCase();var s=status.value||"";var f=fachschaft.value||"";return rows.filter(function(row){return (!q||text(row).indexOf(q)!==-1)&&(!s||row.getAttribute("data-status")===s)&&(!f||row.getAttribute("data-fachschaft")===f);});}'
        . 'function render(){var items=filtered();var pages=Math.max(1,Math.ceil(items.length/pageSize));if(page>pages){page=pages;}tbody.innerHTML="";items.slice((page-1)*pageSize,page*pageSize).forEach(function(row){tbody.appendChild(row.cloneNode(true));});empty.hidden=items.length!==0;pageLabel.textContent=items.length?("Seite "+page+" von "+pages+" · "+items.length+" Einträge"):"Keine Einträge";prev.disabled=page<=1;next.disabled=page>=pages;}'
        . 'function reset(){page=1;render();}'
        . '[search,status,fachschaft].forEach(function(control){control.addEventListener("input",reset);control.addEventListener("change",reset);});'
        . 'prev.addEventListener("click",function(){if(page>1){page--;render();}});'
        . 'next.addEventListener("click",function(){page++;render();});'
        . 'render();'
        . '})();</script>';

    return '<!-- wp:heading --><h2>' . esc_html($title) . '</h2><!-- /wp:heading -->'
        . '<!-- wp:group {"align":"wide"} --><div id="' . esc_attr($table_id) . '" class="wp-block-group alignwide fsfp-unified-overview">'
        . '<div class="fsfp-unified-controls">'
        . '<label>Suche <input type="search" data-unified-search placeholder="Titel, ID, Betrag"></label>'
        . '<label>Status <select data-unified-status>' . fsfp_cli_status_select_options($kind) . '</select></label>'
        . '<label>Fachschaft <select data-unified-fachschaft>' . $fachschaft_select . '</select></label>'
        . '</div>'
        . '<table class="fsfp-table fsfp-unified-table"><thead><tr><th>Fachschaft</th><th>ID</th><th>Titel</th><th>Status</th>' . $date_th . '<th>Betrag</th><th>Aktionen</th></tr></thead><tbody data-unified-body>' . $sources . '</tbody></table>'
        . '<p class="fsfp-unified-empty" data-unified-empty hidden>Keine passenden Einträge gefunden.</p>'
        . '<div class="fsfp-unified-pagination"><button type="button" data-unified-prev>Zurück</button><span data-unified-page></span><button type="button" data-unified-next>Weiter</button></div>'
        . $script
        . '</div><!-- /wp:group -->';
}

function fsfp_cli_related_zahlungen_shortcode(string $zahlung_post_type, string $fachschaft_slug): string
{
    $detail_url = '/dashboard/' . esc_attr($fachschaft_slug) . '/zahlungsanweisung-details/?id={@ID}';
    $template_slug = sanitize_key("fsfp-{$zahlung_post_type}-related-to-beschluss");

    fsfp_cli_upsert_pods_template(
        $template_slug,
        $template_slug,
        '[before]' . "\n"
        . '<table class="fsfp-table fsfp-related-table">' . "\n"
        . '<thead><tr><th>ID</th><th>Titel</th><th>Status</th><th>Betrag</th><th>Aktionen</th></tr></thead>' . "\n"
        . '<tbody>' . "\n"
        . '[/before]' . "\n"
        . '<tr class="fsfp-related-zahlung-row" data-beschluss-id="{@beschluss_ref.ID}">'
        . '<td>{@ID}</td>'
        . '<td><a href="' . $detail_url . '">{@post_title}</a></td>'
        . '<td>{@zahlungs_status}</td>'
        . '<td class="fsfp-related-zahlung-amount">{@betrag}</td>'
        . '<td><a href="' . $detail_url . '">Details</a></td>'
        . '</tr>' . "\n"
        . '[after]' . "\n"
        . '</tbody></table>' . "\n"
        . '[/after]'
    );

    return '<!-- wp:heading {"level":3} --><h3>Zugehörige Zahlungsanweisungen</h3><!-- /wp:heading -->' . "\n"
        . '<!-- wp:paragraph --><p>Diese Liste zeigt alle Zahlungsanweisungen, die diesen Beschluss als Beschluss-Referenz verwenden.</p><!-- /wp:paragraph -->' . "\n"
        . '<!-- wp:shortcode -->' . "\n"
        . '[pods name="' . esc_attr($zahlung_post_type) . '" template="' . esc_attr($template_slug) . '" where="beschluss_ref.ID = {@get.id}" expires="-1" limit="-1" not_found="Keine Zahlungsanweisungen referenzieren diesen Beschluss."]' . "\n"
        . '<!-- /wp:shortcode -->' . "\n";
}

function fsfp_cli_budget_source_shortcode(string $zahlung_post_type): string
{
    $template_slug = sanitize_key("fsfp-{$zahlung_post_type}-budget-source");

    fsfp_cli_upsert_pods_template(
        $template_slug,
        $template_slug,
        '<span class="fsfp-budget-source-row" data-beschluss-id="{@beschluss_ref.ID}" data-payment-amount="{@betrag}"></span>' . "\n"
    );

    return '<div class="fsfp-budget-source" hidden>' . "\n"
        . '<!-- wp:shortcode -->' . "\n"
        . '[pods name="' . esc_attr($zahlung_post_type) . '" template="' . esc_attr($template_slug) . '" expires="-1" limit="-1"]' . "\n"
        . '<!-- /wp:shortcode -->' . "\n"
        . '</div>' . "\n";
}

function fsfp_cli_budget_script(): string
{
    return '<script>(function(){'
        . 'var root=document.currentScript.closest(".fsfp-detail-page");if(!root){return;}'
        . 'function parseAmount(text){var value=(text||"").replace(/ /g,"").replace(/[^0-9,.-]/g,"");if(!value){return 0;}var comma=value.lastIndexOf(","),dot=value.lastIndexOf(".");if(comma>dot){value=value.replace(/[.]/g,"").replace(",",".");}else if(dot>comma){value=value.replace(/,/g,"");}else{value=value.replace(",",".");}var parsed=parseFloat(value);return Number.isFinite(parsed)?parsed:0;}'
        . 'function formatAmount(value){return new Intl.NumberFormat("de-DE",{style:"currency",currency:"EUR"}).format(value);}'
        . 'var budgetEl=root.querySelector("[data-budget-amount]");var openEl=root.querySelector("[data-open-budget]");var paidEl=root.querySelector("[data-paid-budget]");if(!budgetEl||!openEl){return;}'
        . 'var budget=parseAmount(budgetEl.textContent);var marker=root.querySelector("[data-current-beschluss-id]");var current=marker?marker.dataset.currentBeschlussId:"";var paid=0;'
        . 'root.querySelectorAll(".fsfp-related-zahlung-amount").forEach(function(el){paid+=parseAmount(el.textContent);});'
        . 'root.querySelectorAll(".fsfp-budget-source-row").forEach(function(el){if(!current||el.dataset.beschlussId===current){paid+=parseAmount(el.dataset.paymentAmount||el.textContent);}});'
        . 'if(paidEl){paidEl.textContent=formatAmount(paid);}'
        . 'openEl.textContent=formatAmount(budget-paid);'
        . '})();</script>';
}

function fsfp_cli_workflow_metadata_markup(string $post_type, string $kind): string
{
    $template_slug = sanitize_key("fsfp-{$post_type}-workflow-log");

    if ($kind === 'beschluss') {
        fsfp_cli_upsert_pods_template(
            $template_slug,
            $template_slug,
            '<table class="fsfp-table fsfp-workflow-log">'
            . '<thead><tr><th>Schritt</th><th>Status</th><th>Datum</th><th>Person</th><th>Hinweis</th></tr></thead>'
            . '<tbody>'
            . '<tr><td>Erstellt</td><td>Entwurf</td><td>{@post_date}</td><td>{@post_author.display_name}</td><td></td></tr>'
            . '<tr><td>Entscheidung</td><td>{@beschluss_status}</td><td>{@decided_at}</td><td>{@decided_by}</td><td>{@decision_note}</td></tr>'
            . '</tbody></table>'
        );
    } else {
        fsfp_cli_upsert_pods_template(
            $template_slug,
            $template_slug,
            '<table class="fsfp-table fsfp-workflow-log">'
            . '<thead><tr><th>Schritt</th><th>Status</th><th>Datum</th><th>Person</th><th>Hinweis</th></tr></thead>'
            . '<tbody>'
            . '<tr><td>Erstellt</td><td>Entwurf</td><td>{@post_date}</td><td>{@post_author.display_name}</td><td></td></tr>'
            . '<tr><td>Eingereicht</td><td>Eingereicht</td><td>{@submitted_at}</td><td></td><td>{@workflow_note}</td></tr>'
            . '<tr><td>Geprüft</td><td>{@zahlungs_status}</td><td>{@reviewed_at}</td><td>{@reviewed_by}</td><td>{@workflow_note}</td></tr>'
            . '<tr><td>Ausgeführt</td><td>Ausgeführt</td><td>{@executed_at}</td><td>{@executed_by}</td><td>{@workflow_note}</td></tr>'
            . '</tbody></table>'
        );
    }

    return '<!-- wp:heading {"level":3} --><h3>Workflow-Log</h3><!-- /wp:heading -->' . "\n"
        . '<!-- wp:shortcode -->' . "\n"
        . '[pods name="' . esc_attr($post_type) . '" slug="{@get.id}" template="' . esc_attr($template_slug) . '" expires="-1"]' . "\n"
        . '<!-- /wp:shortcode -->' . "\n";
}

function fsfp_cli_detail_page_content(string $post_type, string $kind, string $list_url, string $fachschaft_slug = '', string $related_zahlung_type = ''): string
{
    $date_markup = $kind === 'beschluss' ? '<dt>Datum</dt><dd>{@beschlussdatum}</dd>' : '';
    $status_field = $kind === 'beschluss' ? 'beschluss_status' : 'zahlungs_status';
    $description_field = $kind === 'beschluss' ? 'zweck_beschreibung' : 'verwendungszweck';
    $reference_markup = '';
    $related_markup = '';
    $amount_markup = '<dt>Betrag Zahlungsanweisung</dt><dd>{@betrag}</dd>';

    if ($kind === 'beschluss' && $related_zahlung_type !== '') {
        $related_markup = fsfp_cli_related_zahlungen_shortcode($related_zahlung_type, $fachschaft_slug);
        $amount_markup = '<dt>Betrag Beschlossen</dt><dd data-budget-amount>{@betrag}</dd>'
            . '<dt>Betrag Zahlungsanweisungen</dt><dd data-paid-budget>Wird berechnet...</dd>'
            . '<dt>Betrag Offen</dt><dd data-open-budget>Wird berechnet...</dd>';
    }

    if ($kind === 'zahlung') {
        $beschluss_url = '/dashboard/' . esc_attr($fachschaft_slug) . '/beschluss-details/?id={@beschluss_ref.ID}';
        $reference_markup = '<dt>Beschluss</dt><dd><a href="' . $beschluss_url . '">{@beschluss_ref.post_title}</a></dd>'
            . '<dt>Betrag Beschlossen</dt><dd data-budget-amount>{@beschluss_ref.betrag}</dd>'
            . '<dt>Betrag Offen</dt><dd data-open-budget>Wird berechnet...</dd>'
            . '<dd hidden data-current-beschluss-id="{@beschluss_ref.ID}"></dd>';
        if ($related_zahlung_type !== '') {
            $related_markup = fsfp_cli_budget_source_shortcode($related_zahlung_type);
        }
    }

    return '<!-- wp:group --><div class="wp-block-group fsfp-detail-page">' . "\n"
        . '<!-- wp:html -->' . "\n"
        . '[pods name="' . esc_attr($post_type) . '" slug="{@get.id}"]' . "\n"
        . '<article class="fsfp-entry">'
        . '<h3>{@post_title}</h3>'
        . '<dl>'
        . '<dt>Interne ID</dt><dd>{@ID}</dd>'
        . '<dt>Status</dt><dd>{@' . $status_field . '}</dd>'
        . $date_markup
        . $amount_markup
        . '<dt>Beschreibung</dt><dd>{@' . $description_field . '}</dd>'
        . $reference_markup
        . '<dt>Notizen</dt><dd>{@notes}</dd>'
        . '</dl>'
        . '</article>' . "\n"
        . '[/pods]' . "\n"
        . '<!-- /wp:html -->' . "\n"
        . $related_markup
        . '<!-- wp:html -->' . fsfp_cli_budget_script() . '<!-- /wp:html -->' . "\n"
        . fsfp_cli_workflow_metadata_markup($post_type, $kind)
        . '<!-- wp:paragraph --><p><a class="wp-block-button__link wp-element-button" data-fsfp-back-link href="' . esc_url($list_url) . '">Zur Liste zurück</a></p><!-- /wp:paragraph -->' . "\n"
        . fsfp_cli_contextual_back_link_script($list_url)
        . '</div><!-- /wp:group -->';
}

function fsfp_cli_workflow_overview(string $kind): string
{
    $items = $kind === 'beschluss'
        ? ['Entwurf', 'Genehmigt', 'Abgelehnt']
        : ['Entwurf', 'Eingereicht', 'Rückfrage', 'Stoniert', 'Ausgeführt'];

    $badges = '';
    foreach ($items as $index => $item) {
        if ($index > 0) {
            $badges .= '<span class="fsfp-status-flow__arrow">→</span>';
        }
        $badges .= '<span class="fsfp-status-flow__badge">' . esc_html($item) . '</span>';
    }

    return '<!-- wp:group --><div class="wp-block-group fsfp-status-flow">' . $badges . '</div><!-- /wp:group -->';
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
    return '<!-- wp:shortcode -->' . "\n"
        . '[members_access role="' . esc_attr(implode(',', array_values(array_unique($roles)))) . '"]' . "\n"
        . $content . "\n"
        . '[/members_access]' . "\n"
        . '<!-- /wp:shortcode -->';
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
    return '<!-- wp:group --><div class="wp-block-group fsfp-edit-page"><style>.fsfp-edit-page__form[hidden]{display:none}</style><!-- wp:paragraph --><p>Öffne einen Datensatz über den Bearbeiten-Link in der Liste. Diese Seite lädt einen vorhandenen Eintrag, wenn sie mit <code>?id=123</code> aufgerufen wird.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p><a class="wp-block-button__link wp-element-button" data-fsfp-back-link href="' . esc_url($list_url) . '">Zur Liste zurück</a></p><!-- /wp:paragraph -->'
        . fsfp_cli_contextual_back_link_script($list_url)
        . '<div class="fsfp-edit-page__notice"><!-- wp:paragraph --><p>Kein Datensatz ausgewählt. Bitte nutze den Bearbeiten-Link in der Liste.</p><!-- /wp:paragraph --></div><div class="fsfp-edit-page__form" hidden>'
        . '[pods name="' . esc_attr($post_type) . '" form="true" slug="{@get.id}" fields="' . esc_attr($fields) . '" thank_you="' . esc_url($list_url) . '" label="Änderungen speichern"]'
        . '</div><script>(function(){var params=new URLSearchParams(window.location.search);var id=params.get("id");var form=document.querySelector(".fsfp-edit-page__form");var notice=document.querySelector(".fsfp-edit-page__notice");if(!form||!notice){return;}if(id&&id.length){form.hidden=false;notice.hidden=true;}else{form.hidden=true;notice.hidden=false;}})();</script></div><!-- /wp:group -->';
}

function fsfp_cli_role_gated_edit_form_page(string $post_type, array $forms, string $list_url): string
{
    $content = '<!-- wp:group --><div class="wp-block-group fsfp-edit-page"><style>.fsfp-edit-page__form[hidden]{display:none}</style><!-- wp:paragraph --><p>Öffne einen Datensatz über den Workflow-Link in der Liste. Diese Seite lädt einen vorhandenen Eintrag, wenn sie mit <code>?id=123</code> aufgerufen wird.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p><a class="wp-block-button__link wp-element-button" data-fsfp-back-link href="' . esc_url($list_url) . '">Zur Liste zurück</a></p><!-- /wp:paragraph -->'
        . fsfp_cli_contextual_back_link_script($list_url)
        . '<div class="fsfp-edit-page__notice"><!-- wp:paragraph --><p>Kein Datensatz ausgewählt. Bitte nutze den Link in der Liste.</p><!-- /wp:paragraph --></div>';

    foreach ($forms as $form) {
        $content .= fsfp_cli_members_access_block(
            $form['roles'],
            '<div class="fsfp-action-panel"><h3>' . esc_html($form['title']) . '</h3><div class="fsfp-edit-page__form" hidden>'
            . '[pods name="' . esc_attr($post_type) . '" form="true" slug="{@get.id}" fields="' . esc_attr($form['fields']) . '" thank_you="' . esc_url($list_url) . '" label="' . esc_attr($form['label']) . '"]'
            . '</div></div>'
        );
    }

    return $content
        . '<script>(function(){var params=new URLSearchParams(window.location.search);var id=params.get("id");var forms=document.querySelectorAll(".fsfp-edit-page__form");var notice=document.querySelector(".fsfp-edit-page__notice");if(!notice){return;}if(id&&id.length){forms.forEach(function(form){form.hidden=false;});notice.hidden=true;}else{forms.forEach(function(form){form.hidden=true;});notice.hidden=false;}})();</script></div><!-- /wp:group -->';
}

function fsfp_cli_workflow_action_buttons(string $create_url, array $roles): string
{
    return '<!-- wp:buttons --><div class="wp-block-buttons">'
        . '<!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url($create_url) . '">Neu erstellen</a></div><!-- /wp:button -->'
        . '</div><!-- /wp:buttons -->';
}

$fachschaften = fsfp_cli_load_fachschaften();
$workflow_caps = [];
$read_workflow_caps = [];
$edit_workflow_caps = [];
$asta_workflow_caps = [];

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

    foreach (fsfp_cli_workflow_types($slug) as $kind => $post_type) {
        $capability_type = fsfp_cli_capability_type($post_type);
        $workflow_caps = array_merge($workflow_caps, fsfp_cli_post_type_caps($capability_type));
        $read_workflow_caps = array_merge($read_workflow_caps, fsfp_cli_read_caps($capability_type));
        $edit_workflow_caps = array_merge($edit_workflow_caps, fsfp_cli_edit_caps($capability_type));

        if ($kind === 'beschluss') {
            $asta_workflow_caps = array_merge($asta_workflow_caps, fsfp_cli_read_caps($capability_type));
        } else {
            $asta_workflow_caps = array_merge($asta_workflow_caps, fsfp_cli_edit_caps($capability_type));
        }
    }
}

$fachschaft_caps = fsfp_cli_post_type_caps('fachschaft_record');
$administrator_caps = get_role('administrator') ? array_keys(get_role('administrator')->capabilities) : ['read', 'manage_options'];
fsfp_cli_add_caps('administrator', [fsfp_cli_admin_edit_access_cap()]);

fsfp_cli_sync_caps('portal_admin', array_merge($administrator_caps, [fsfp_cli_admin_edit_access_cap()], $fachschaft_caps, $workflow_caps));
fsfp_cli_sync_caps('asta_finance', array_merge(['read', 'upload_files', fsfp_cli_admin_edit_access_cap()], $asta_workflow_caps));
fsfp_cli_sync_caps('asta_reviewer', array_merge(['read', fsfp_cli_admin_edit_access_cap()], $asta_workflow_caps));
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

fsfp_cli_configure_meta_ledger($fachschaften);

$dashboard_blocks = '';
$menu_items = [
    ['title' => 'Dashboard', 'url' => home_url('/dashboard/')],
    ['title' => 'Logout', 'url' => home_url('/wp-login.php?action=logout')],
];
$block_menu_items = [
    ['title' => 'Dashboard', 'url' => home_url('/dashboard/')],
    ['title' => 'Beschlüsse', 'url' => '#', 'className' => 'fsfp-nav-workflow fsfp-nav-beschluesse is-hidden'],
    ['title' => 'Zahlungsanweisungen', 'url' => '#', 'className' => 'fsfp-nav-workflow fsfp-nav-zahlungsanweisungen is-hidden'],
];

foreach ($fachschaften as $fachschaft) {
    $slug = sanitize_key($fachschaft['slug']);
    $label = $fachschaft['label'];

    $dashboard_blocks .= fsfp_cli_members_access_block(
        fsfp_cli_fachschaft_view_roles($slug),
        fsfp_cli_dashboard_card($label, home_url("/dashboard/{$slug}/"), 'Öffnen')
    );
}

$dashboard_blocks .= fsfp_cli_members_access_block(
    fsfp_cli_global_overview_roles(),
    fsfp_cli_dashboard_card('AStA / Gesamtübersicht', home_url('/dashboard/beschluesse/'), 'Alle Beschlüsse öffnen')
    . fsfp_cli_dashboard_card('AStA / Zahlungsanweisungen', home_url('/dashboard/zahlungsanweisungen/'), 'Alle Zahlungsanweisungen öffnen')
);
$dashboard_blocks .= fsfp_cli_members_access_block(
    ['fs_portal_empty'],
    '<!-- wp:paragraph --><p>Ihr Konto ist keiner Fachschaft zugeordnet. Bitte wenden Sie sich an die Portal-Administration.</p><!-- /wp:paragraph -->'
);
$block_menu_items[] = ['type' => 'html', 'content' => fsfp_cli_navigation_target_script($fachschaften)];
$block_menu_items[] = ['title' => 'Logout', 'url' => home_url('/wp-login.php?action=logout')];

$dashboard_content = '<!-- wp:heading --><h2>Fachschafts-Finanzportal</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Beschlüsse, Belege und Zahlungsanweisungen werden nach Fachschaft getrennt verwaltet.</p><!-- /wp:paragraph -->
'
    . $dashboard_blocks;
$dashboard_id = fsfp_cli_upsert_page('dashboard', 'Dashboard', $dashboard_content);
fsfp_cli_delete_child_pages($dashboard_id);

update_option('show_on_front', 'page');
update_option('page_on_front', $dashboard_id);
update_option('page_for_posts', 0);

$global_beschluesse = fsfp_cli_unified_overview_page('beschluss', $fachschaften);
$global_zahlungen = fsfp_cli_unified_overview_page('zahlung', $fachschaften);

foreach ($fachschaften as $fachschaft) {
    $slug = sanitize_key($fachschaft['slug']);
    $label = $fachschaft['label'];
    $types = fsfp_cli_workflow_types($slug);
    $fachschaft_view_roles = fsfp_cli_fachschaft_access_roles($slug);
    $fachschaft_nav_roles = fsfp_cli_fachschaft_view_roles($slug);
    $edit_roles = ["fs_{$slug}_finance", 'administrator', 'portal_admin'];
    $zahlung_create_roles = ["fs_{$slug}_finance", ...fsfp_cli_global_beschluss_edit_roles()];
    $zahlung_edit_roles = ["fs_{$slug}_finance", ...fsfp_cli_global_zahlung_edit_roles()];
    $zahlung_review_roles = fsfp_cli_global_zahlung_edit_roles();
    $view_only_roles = array_values(array_diff($fachschaft_view_roles, $edit_roles));
    $zahlung_view_only_roles = array_values(array_diff($fachschaft_view_roles, $zahlung_edit_roles));

    $fachschaft_id = fsfp_cli_upsert_page($slug, $label, '<!-- wp:heading --><h2>' . esc_html($label) . '</h2><!-- /wp:heading -->
<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/dashboard/' . esc_attr($slug) . '/beschluesse/">Beschlüsse</a></div><!-- /wp:button --><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/dashboard/' . esc_attr($slug) . '/zahlungsanweisungen/">Zahlungsanweisungen</a></div><!-- /wp:button --></div><!-- /wp:buttons -->', $dashboard_id);
    fsfp_cli_restrict_page_to_roles($fachschaft_id, $fachschaft_nav_roles);

    $beschluss_list = '<!-- wp:heading --><h2>Beschlüsse</h2><!-- /wp:heading -->'
        . fsfp_cli_workflow_overview('beschluss')
        . fsfp_cli_list_intro('beschluss')
        . fsfp_cli_members_access_block($view_only_roles, fsfp_cli_list_shortcode($types['beschluss'], 'beschluss', $slug, false, true))
        . fsfp_cli_members_access_block(
            $edit_roles,
            fsfp_cli_workflow_action_buttons(
                home_url("/dashboard/{$slug}/beschluss-erstellen/"),
                $edit_roles
            )
        )
        . fsfp_cli_members_access_block($edit_roles, fsfp_cli_list_shortcode($types['beschluss'], 'beschluss', $slug, true, false));
    $beschluss_list_id = fsfp_cli_upsert_page('beschluesse', 'Beschlüsse', $beschluss_list, $fachschaft_id);
    fsfp_cli_restrict_page_to_roles($beschluss_list_id, $fachschaft_nav_roles);

    $beschluss_detail_id = fsfp_cli_upsert_page('beschluss-details', 'Beschluss Details', fsfp_cli_detail_page_content($types['beschluss'], 'beschluss', home_url("/dashboard/{$slug}/beschluesse/"), $slug, $types['zahlung']), $fachschaft_id);
    fsfp_cli_restrict_page_to_roles($beschluss_detail_id, $fachschaft_view_roles);

    $beschluss_create_fields = 'post_title,beschlussdatum,betrag,zweck_beschreibung,belege,notes';
    $beschluss_form_fields = 'post_title,beschlussdatum,betrag,zweck_beschreibung,beschluss_status,decided_at,decided_by,decision_note,belege,notes';
    $beschluss_create_id = fsfp_cli_upsert_page('beschluss-erstellen', 'Beschluss erstellen', fsfp_cli_form_shortcode($types['beschluss'], $beschluss_create_fields, home_url("/dashboard/{$slug}/beschluesse/?created=1")), $fachschaft_id);
    fsfp_cli_restrict_page_to_roles($beschluss_create_id, $edit_roles);
    $beschluss_edit_id = fsfp_cli_upsert_page('beschluss-bearbeiten', 'Beschluss bearbeiten', fsfp_cli_edit_form_page($types['beschluss'], $beschluss_form_fields, home_url("/dashboard/{$slug}/beschluesse/")), $fachschaft_id);
    fsfp_cli_restrict_page_to_roles($beschluss_edit_id, $edit_roles);

    $zahlung_list = '<!-- wp:heading --><h2>Zahlungsanweisungen</h2><!-- /wp:heading -->'
        . fsfp_cli_workflow_overview('zahlung')
        . fsfp_cli_list_intro('zahlung')
        . fsfp_cli_members_access_block($zahlung_view_only_roles, fsfp_cli_list_shortcode($types['zahlung'], 'zahlung', $slug, false, true))
        . fsfp_cli_members_access_block(
            $zahlung_create_roles,
            '<div class="fsfp-action-panel"><h3>Zahlungsanweisung vorbereiten</h3>'
            . fsfp_cli_workflow_action_buttons(
                home_url("/dashboard/{$slug}/zahlungsanweisung-erstellen/"),
                $zahlung_create_roles
            )
            . '</div>'
        )
        . fsfp_cli_members_access_block(
            $zahlung_review_roles,
            '<div class="fsfp-action-panel"><h3>AStA-Prüfung</h3><p>Rückfrage stellen oder Zahlung als ausgeführt markieren.</p></div>'
        )
        . fsfp_cli_members_access_block(["fs_{$slug}_finance", ...fsfp_cli_global_beschluss_edit_roles()], fsfp_cli_list_shortcode($types['zahlung'], 'zahlung', $slug, true, false, 'Bearbeiten / Einreichen / Stornieren'))
        . fsfp_cli_members_access_block(['asta_finance', 'asta_reviewer'], fsfp_cli_list_shortcode($types['zahlung'], 'zahlung', $slug, true, false, 'Rückfrage / Ausgeführt'));
    $zahlung_list_id = fsfp_cli_upsert_page('zahlungsanweisungen', 'Zahlungsanweisungen', $zahlung_list, $fachschaft_id);
    fsfp_cli_restrict_page_to_roles($zahlung_list_id, $fachschaft_nav_roles);

    $zahlung_detail_id = fsfp_cli_upsert_page('zahlungsanweisung-details', 'Zahlungsanweisung Details', fsfp_cli_detail_page_content($types['zahlung'], 'zahlung', home_url("/dashboard/{$slug}/zahlungsanweisungen/"), $slug, $types['zahlung']), $fachschaft_id);
    fsfp_cli_restrict_page_to_roles($zahlung_detail_id, $fachschaft_view_roles);

    $zahlung_create_fields = 'post_title,betrag,verwendungszweck,belege,beschluss_ref,notes';
    $zahlung_finance_fields = 'post_title,betrag,verwendungszweck,zahlungs_status,submitted_at,belege,beschluss_ref,workflow_note,notes';
    $zahlung_reviewer_fields = 'zahlungs_status,reviewed_at,reviewed_by,executed_at,executed_by,workflow_note,notes';
    $zahlung_create_id = fsfp_cli_upsert_page('zahlungsanweisung-erstellen', 'Zahlungsanweisung erstellen', fsfp_cli_form_shortcode($types['zahlung'], $zahlung_create_fields, home_url("/dashboard/{$slug}/zahlungsanweisungen/?created=1")), $fachschaft_id);
    fsfp_cli_restrict_page_to_roles($zahlung_create_id, $zahlung_create_roles);
    $zahlung_edit_id = fsfp_cli_upsert_page('zahlungsanweisung-bearbeiten', 'Zahlungsanweisung bearbeiten', fsfp_cli_role_gated_edit_form_page($types['zahlung'], [
        [
            'roles' => ["fs_{$slug}_finance", ...fsfp_cli_global_beschluss_edit_roles()],
            'title' => 'Fachschaft: bearbeiten, einreichen oder stornieren',
            'fields' => $zahlung_finance_fields,
            'label' => 'Änderungen speichern',
        ],
        [
            'roles' => ['asta_finance', 'asta_reviewer'],
            'title' => 'AStA: Rückfrage oder Ausführung',
            'fields' => $zahlung_reviewer_fields,
            'label' => 'Workflowstatus speichern',
        ],
    ], home_url("/dashboard/{$slug}/zahlungsanweisungen/")), $fachschaft_id);
    fsfp_cli_restrict_page_to_roles($zahlung_edit_id, $zahlung_edit_roles);

}

$global_beschluesse_id = fsfp_cli_upsert_page('beschluesse', 'Alle Beschlüsse', $global_beschluesse, $dashboard_id);
fsfp_cli_restrict_page_to_roles($global_beschluesse_id, fsfp_cli_global_overview_roles());
$global_zahlungen_id = fsfp_cli_upsert_page('zahlungsanweisungen', 'Alle Zahlungsanweisungen', $global_zahlungen, $dashboard_id);
fsfp_cli_restrict_page_to_roles($global_zahlungen_id, fsfp_cli_global_overview_roles());

fsfp_cli_ensure_menu('Portal Navigation', $menu_items);
fsfp_cli_ensure_block_navigation('Portal Navigation', $block_menu_items);

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
.fsfp-unified-controls { display: flex; flex-wrap: wrap; align-items: end; gap: 0.75rem; margin: 1rem 0; padding: 1rem; border: 1px solid #d8dee4; background: #f6f8fa; }
.fsfp-unified-controls label { display: grid; gap: 0.25rem; min-width: 12rem; margin: 0; font-weight: 600; color: #334155; }
.fsfp-unified-controls input,
.fsfp-unified-controls select { min-height: 2.5rem; padding: 0.45rem 0.65rem; border: 1px solid #cbd5e1; border-radius: 4px; font: inherit; font-weight: 400; }
.fsfp-unified-controls input[type=search] { min-width: 18rem; }
.fsfp-unified-empty { margin: 1rem 0; color: #475569; }
.fsfp-unified-pagination { display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; margin: 0 0 1.5rem; }
.fsfp-unified-pagination button { min-height: 2.25rem; padding: 0.35rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 4px; background: #fff; cursor: pointer; }
.fsfp-unified-pagination button:disabled { cursor: not-allowed; opacity: 0.5; }
.fsfp-status-flow { display: flex; flex-wrap: wrap; align-items: center; gap: 0.4rem; margin: 0.75rem 0 1rem; }
.fsfp-status-flow__badge { display: inline-flex; align-items: center; min-height: 2rem; padding: 0.25rem 0.65rem; border: 1px solid #cbd5e1; border-radius: 999px; background: #fff; color: #1f2937; font-weight: 600; font-size: 0.9rem; }
.fsfp-status-flow__arrow { color: #64748b; font-weight: 700; }
.fsfp-action-panel { margin: 1rem 0; padding: 1rem; border: 1px solid #d8dee4; border-radius: 6px; background: #f8fafc; }
.fsfp-action-panel h3 { margin: 0 0 0.5rem; font-size: 1.05rem; }
.fsfp-action-panel p { margin: 0.25rem 0 0.75rem; color: #475569; }
.fsfp-nav-workflow.is-hidden { display: none !important; }
.fsfp-table { width: 100%; border-collapse: collapse; margin: 1rem 0 1.5rem; font-size: 0.95rem; table-layout: auto; }
.fsfp-table th, .fsfp-table td { padding: 0.85rem; border-bottom: 1px solid #d8dee4; text-align: left; vertical-align: top; }
.fsfp-table th { background-color: #f6f8fa; color: #24292f; font-weight: 600; }
.fsfp-table tbody tr:hover { background: #f6f8fa; }
.fsfp-table td:last-child { white-space: nowrap; }
.wp-block-buttons { margin-bottom: 1rem; }
.fsfp-detail-page dt { font-weight: bold; margin-top: 1rem; }
.fsfp-detail-page dd { margin-left: 0; margin-bottom: 0.5rem; }
.fsfp-detail-page [data-open-budget],
.fsfp-detail-page [data-paid-budget] { font-weight: 700; color: #0f766e; }
.fsfp-workflow-log { margin-top: 0.75rem; }
.fsfp-workflow-log th,
.fsfp-workflow-log td { font-size: 0.92rem; }
.fsfp-workflow-log td:first-child { font-weight: 700; color: #334155; }
.fsfp-workflow-log td:nth-child(2) { white-space: nowrap; }
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

    foreach (['fachschaft', 'beschlussdatum', 'betrag', 'zweck_beschreibung', 'notes'] as $field) {
        update_post_meta($post_id, $field, $item[$field] ?? '');
    }

    update_post_meta($post_id, 'beschluss_status', $item['status'] ?? 'draft');
}

fsfp_cli_publish_existing_workflow_posts($fachschaften);
fsfp_cli_normalize_workflow_statuses($fachschaften);

flush_rewrite_rules();

WP_CLI::success('Portal content configured.');
