<?php
/**
 * Setup-time helpers for FS-Finanzportal.
 */

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
