<?php
/**
 * Generated dashboard, navigation, and page permission verification.
 */

function fs_finanzportal_verify_pages(array $fachschaften): array
{
    $dashboard = get_page_by_path('dashboard', OBJECT, 'page');
    if (!$dashboard || str_contains($dashboard->post_content, '[pods_table')) {
        fs_finanzportal_verify_fail('Dashboard page is missing or still depends on a custom table shortcode.');
    }
    
    if (str_contains($dashboard->post_content, 'wp-admin')) {
        fs_finanzportal_verify_fail('Dashboard page must not link to wp-admin.');
    }
    
    if (!str_contains($dashboard->post_content, '[members_access role=')
        || !str_contains($dashboard->post_content, 'fs_informatik_reader')
        || !str_contains($dashboard->post_content, 'fs_portal_empty')
    ) {
        fs_finanzportal_verify_fail('Dashboard must use Members role-gated blocks.');
    }
    
    if (!str_contains($dashboard->post_content, 'Alle Beschlüsse öffnen')
        || !str_contains($dashboard->post_content, 'Alle Zahlungsanweisungen öffnen')
        || !str_contains($dashboard->post_content, 'Berichte öffnen')
    ) {
        fs_finanzportal_verify_fail('Dashboard must link AStA staff to the unified overview, reporting, and operations pages.');
    }

    if (!str_contains($dashboard->post_content, '[members_access role="administrator,portal_admin"]')
        || !str_contains($dashboard->post_content, 'href="' . home_url('/dashboard/betrieb/') . '"')
        || !str_contains($dashboard->post_content, '>Betrieb<')
    ) {
        fs_finanzportal_verify_fail('Dashboard must include the admin-only Betrieb card.');
    }
    
    $menu = wp_get_nav_menu_object('Portal Navigation');
    if (!$menu) {
        fs_finanzportal_verify_fail('Portal Navigation menu is missing.');
    }
    
    $menu_items = wp_get_nav_menu_items((int) $menu->term_id) ?: [];
    $menu_urls = array_map(fn($item) => (string) $item->url, $menu_items);
    
    if (count($menu_items) !== 2) {
        fs_finanzportal_verify_fail('Portal Navigation must only contain Dashboard and Logout.');
    }
    
    foreach ($menu_urls as $url) {
        if (str_contains($url, '/dashboard/beschluesse/')
            || str_contains($url, '/dashboard/zahlungsanweisungen/')
            || str_contains($url, '/dashboard/informatik/')
            || str_contains($url, '/dashboard/maschinenbau/')
            || str_contains($url, '/dashboard/philosophie/')
        ) {
            fs_finanzportal_verify_fail('Portal Navigation must not expose Fachschaft or global workflow links.');
        }
    }
    
    $block_navigation = get_page_by_path('portal-navigation', OBJECT, 'wp_navigation');
    if (!$block_navigation) {
        fs_finanzportal_verify_fail('Portal block navigation is missing.');
    }
    
    if (!str_contains($block_navigation->post_content, 'fsfp-nav-beschluesse')
        || !str_contains($block_navigation->post_content, 'fsfp-nav-zahlungsanweisungen')
        || !str_contains($block_navigation->post_content, 'scopedBaseFromPath')
        || !str_contains($block_navigation->post_content, 'dashboardBaseFromContent')
        || !str_contains($block_navigation->post_content, 'pathParts')
    ) {
        fs_finanzportal_verify_fail('Portal block navigation must expose dynamic workflow header links.');
    }
    
    if (str_contains($block_navigation->post_content, '[members_access')
        || str_contains($block_navigation->post_content, '[/members_access]')
        || str_contains($block_navigation->post_content, '/dashboard/informatik/beschluesse/')
    ) {
        fs_finanzportal_verify_fail('Portal block navigation must not render Members shortcodes or duplicate scoped static links.');
    }
    
    wp_set_current_user(0);
    
    $members_settings = get_option('members_settings');
    if (!is_array($members_settings) || empty($members_settings['content_permissions'])) {
        fs_finanzportal_verify_fail('Members content permissions must be enabled.');
    }
    
    $global_pages = [
        fs_finanzportal_page_by_path('dashboard/beschluesse'),
        fs_finanzportal_page_by_path('dashboard/zahlungsanweisungen'),
        fs_finanzportal_page_by_path('dashboard/berichte'),
    ];
    
    foreach ($global_pages as $global_page) {
        fs_finanzportal_verify_page_roles($global_page, fs_finanzportal_global_overview_roles());
    }

    $operations_page = fs_finanzportal_page_by_path('dashboard/betrieb');
    fs_finanzportal_verify_page_roles($operations_page, ['administrator', 'portal_admin']);
    
    $expected_direct_children = ['berichte', 'beschluesse', 'betrieb', 'informatik', 'maschinenbau', 'philosophie', 'zahlungsanweisungen'];
    $actual_direct_children = get_posts([
        'post_type' => 'page',
        'post_status' => 'any',
        'post_parent' => $dashboard->ID,
        'posts_per_page' => -1,
    ]);
    $actual_direct_child_slugs = array_map(fn($page) => $page->post_name, $actual_direct_children);
    sort($actual_direct_child_slugs);
    sort($expected_direct_children);
    
    if ($actual_direct_child_slugs !== $expected_direct_children) {
        fs_finanzportal_verify_fail('Dashboard contains stale or unexpected child pages: ' . implode(',', $actual_direct_child_slugs));
    }
    
    $restricted_pages_by_fachschaft = [];
    
    foreach ($fachschaften as $fachschaft) {
        $slug = sanitize_key($fachschaft['slug']);
        $restricted_pages_by_fachschaft[$slug] = [];
    
        foreach (fs_finanzportal_workflow_types($slug) as $post_type) {
            $post_type_object = get_post_type_object($post_type);
            if (!$post_type_object) {
                fs_finanzportal_verify_fail("Workflow post type {$post_type} is missing.");
            }
    
            if ($post_type_object->publicly_queryable || $post_type_object->has_archive || $post_type_object->rewrite) {
                fs_finanzportal_verify_fail("Workflow post type {$post_type} must not expose direct public routes.");
            }
        }
    
        foreach ([
            "dashboard/{$slug}",
            "dashboard/{$slug}/beschluesse",
            "dashboard/{$slug}/beschluss-details",
            "dashboard/{$slug}/beschluss-erstellen",
            "dashboard/{$slug}/beschluss-bearbeiten",
            "dashboard/{$slug}/zahlungsanweisungen",
            "dashboard/{$slug}/zahlungsanweisung-details",
            "dashboard/{$slug}/zahlungsanweisung-erstellen",
            "dashboard/{$slug}/zahlungsanweisung-bearbeiten",
        ] as $path) {
            $portal_page = fs_finanzportal_page_by_path($path);
            $restricted_pages_by_fachschaft[$slug][] = $portal_page;
    
            $expected_page_roles = fs_finanzportal_fachschaft_access_roles($slug);
            if ($path === "dashboard/{$slug}"
                || str_ends_with($path, '/beschluesse')
                || str_ends_with($path, '/zahlungsanweisungen')
            ) {
                $expected_page_roles = fs_finanzportal_fachschaft_view_roles($slug);
            } elseif (str_contains($path, 'beschluss-erstellen') || str_contains($path, 'beschluss-bearbeiten')) {
                $expected_page_roles = array_merge(["fs_{$slug}_finance"], ['administrator', 'portal_admin']);
            } elseif (str_contains($path, 'zahlungsanweisung-erstellen')) {
                $expected_page_roles = array_merge(["fs_{$slug}_finance"], fs_finanzportal_global_beschluss_edit_roles());
            } elseif (str_contains($path, 'zahlungsanweisung-bearbeiten')) {
                $expected_page_roles = array_merge(["fs_{$slug}_finance"], fs_finanzportal_global_zahlung_edit_roles());
            }
    
            fs_finanzportal_verify_page_roles($portal_page, $expected_page_roles);
    
            if (str_contains($portal_page->post_content, '[pods_table')) {
                fs_finanzportal_verify_fail("Frontend portal page {$path} still depends on custom runtime shortcode.");
            }
    
            if (str_contains($portal_page->post_content, 'orderby=')) {
                fs_finanzportal_verify_fail("Frontend portal page {$path} contains unsafe Pods orderby shortcode SQL.");
            }
    
            if (str_contains($portal_page->post_content, 'post_status=')) {
                fs_finanzportal_verify_fail("Frontend portal page {$path} must not override post_status in Pods shortcodes.");
            }
    
            if (str_ends_with($path, '/beschluesse') || str_ends_with($path, '/zahlungsanweisungen')) {
                if (!str_contains($portal_page->post_content, 'fsfp-scoped-overview')
                    || !str_contains($portal_page->post_content, 'data-scoped-search')
                    || !str_contains($portal_page->post_content, 'data-scoped-status')
                    || !str_contains($portal_page->post_content, 'data-scoped-export')
                    || !str_contains($portal_page->post_content, 'data-scoped-prev')
                    || !str_contains($portal_page->post_content, 'data-scoped-next')
                    || !str_contains($portal_page->post_content, 'data-fsfp-table="scoped"')
                    || str_contains($portal_page->post_content, 'search="1"')
                    || str_contains($portal_page->post_content, 'filters=')
                    || str_contains($portal_page->post_content, 'pagination=')
                ) {
                    fs_finanzportal_verify_fail("Frontend list page {$path} must use the shared client-side table controls.");
                }

                if (!str_contains($portal_page->post_content, 'replace(/\\s+/g," ")')
                    || !str_contains($portal_page->post_content, '/[",\\n;]/.test(value)')
                    || !str_contains($portal_page->post_content, '"\\ufeff"+lines.join("\\n")')
                ) {
                    fs_finanzportal_verify_fail("Frontend list page {$path} must preserve CSV export JavaScript escape sequences.");
                }
            }
    
            if (str_contains($portal_page->post_content, '{@permalink}')) {
                fs_finanzportal_verify_fail("Frontend portal page {$path} must not expose direct workflow permalinks.");
            }
    
            if (str_contains($path, 'beschluss-erstellen') && str_contains($portal_page->post_content, 'beschluss_status')) {
                fs_finanzportal_verify_fail("Beschluss create page {$path} must not expose the status field.");
            }
    
            if (str_contains($path, 'zahlungsanweisung-erstellen') && str_contains($portal_page->post_content, 'zahlungs_status')) {
                fs_finanzportal_verify_fail("Zahlungsanweisung create page {$path} must not expose the status field.");
            }
        }
    }
    
    foreach ($global_pages as $global_page) {
        if (str_contains($global_page->post_content, 'orderby=')) {
            fs_finanzportal_verify_fail("Global portal page {$global_page->post_name} contains unsafe Pods orderby shortcode SQL.");
        }
    
        if (str_contains($global_page->post_content, 'post_status=')) {
            fs_finanzportal_verify_fail("Global portal page {$global_page->post_name} must not override post_status in Pods shortcodes.");
        }
    
        if (str_contains($global_page->post_content, '{@permalink}')) {
            fs_finanzportal_verify_fail("Global portal page {$global_page->post_name} must not expose direct workflow permalinks.");
        }
    
        if (!str_contains($global_page->post_content, 'fsfp-unified-overview')
            || !str_contains($global_page->post_content, 'data-unified-search')
            || !str_contains($global_page->post_content, 'data-unified-status')
            || !str_contains($global_page->post_content, 'data-unified-fachschaft')
            || !str_contains($global_page->post_content, 'data-unified-export')
            || !str_contains($global_page->post_content, 'data-unified-prev')
            || !str_contains($global_page->post_content, 'data-unified-next')
            || !str_contains($global_page->post_content, 'data-fsfp-table="unified"')
            || !str_contains($global_page->post_content, '<tbody data-unified-body>')
        ) {
            fs_finanzportal_verify_fail("Global portal page {$global_page->post_name} must render a unified overview table with filters and pagination.");
        }

        if (!str_contains($global_page->post_content, 'replace(/\\s+/g," ")')
            || !str_contains($global_page->post_content, '/[",\\n;]/.test(value)')
            || !str_contains($global_page->post_content, '"\\ufeff"+lines.join("\\n")')
        ) {
            fs_finanzportal_verify_fail("Global portal page {$global_page->post_name} must preserve CSV export JavaScript escape sequences.");
        }
    
        if (str_contains($global_page->post_content, '<h3>Fachschaft Informatik</h3>')
            || str_contains($global_page->post_content, '<h3>Fachschaft Maschinenbau</h3>')
            || str_contains($global_page->post_content, '<h3>Fachschaft Philosophie</h3>')
        ) {
            fs_finanzportal_verify_fail("Global portal page {$global_page->post_name} must not render one visible table section per Fachschaft.");
        }
    }
    
    $global_beschluss_rows_template = get_page_by_path('fsfp-global-b_informatik-beschluss-rows', OBJECT, '_pods_template');
    if (!$global_beschluss_rows_template
        || !str_contains($global_beschluss_rows_template->post_content, 'return_to=%2Fdashboard%2Fbeschluesse%2F')
    ) {
        fs_finanzportal_verify_fail('Global Beschluss overview row links must preserve the unified overview as return target.');
    }
    
    $global_zahlung_rows_template = get_page_by_path('fsfp-global-za_informatik-zahlung-rows', OBJECT, '_pods_template');
    if (!$global_zahlung_rows_template
        || !str_contains($global_zahlung_rows_template->post_content, 'return_to=%2Fdashboard%2Fzahlungsanweisungen%2F')
    ) {
        fs_finanzportal_verify_fail('Global payment overview row links must preserve the unified overview as return target.');
    }

    return [
        'global_pages' => $global_pages,
        'restricted_pages_by_fachschaft' => $restricted_pages_by_fachschaft,
    ];
}
