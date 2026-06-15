<?php
/**
 * Portal page and navigation generation.
 */

function fsfp_cli_ensure_portal_pages(array $fachschaften): void
{
    $forms_config = fsfp_cli_load_json_config('portal/forms.json', 'Portal forms config JSON is invalid.');
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
        . fsfp_cli_dashboard_card('AStA / Berichte', home_url('/dashboard/berichte/'), 'Berichte öffnen')
    );
    $dashboard_blocks .= fsfp_cli_members_access_block(
        ['administrator', 'portal_admin'],
        fsfp_cli_dashboard_card('Betrieb', home_url('/dashboard/betrieb/'), 'Öffnen')
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
    
    $global_beschluesse = fsfp_cli_unified_overview_page('beschluss', $fachschaften)
        . fsfp_cli_budget_report($fachschaften);
    $global_zahlungen = fsfp_cli_payment_queue_links(home_url('/dashboard/zahlungsanweisungen/'), true)
        . fsfp_cli_unified_overview_page('zahlung', $fachschaften);
    $global_berichte = fsfp_cli_reporting_page($fachschaften);
    
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
    
        $beschluss_create_fields = $forms_config['beschluss']['create'];
        $beschluss_form_fields = $forms_config['beschluss']['edit'];
        $beschluss_create_id = fsfp_cli_upsert_page('beschluss-erstellen', 'Beschluss erstellen', fsfp_cli_form_page('beschluss', $types['beschluss'], $beschluss_create_fields, home_url("/dashboard/{$slug}/beschluesse/?created=1"), 'Beschluss erfassen'), $fachschaft_id);
        fsfp_cli_restrict_page_to_roles($beschluss_create_id, $edit_roles);
        $beschluss_edit_id = fsfp_cli_upsert_page('beschluss-bearbeiten', 'Beschluss bearbeiten', fsfp_cli_edit_form_page($types['beschluss'], $beschluss_form_fields, home_url("/dashboard/{$slug}/beschluesse/")), $fachschaft_id);
        fsfp_cli_restrict_page_to_roles($beschluss_edit_id, $edit_roles);
    
        $zahlung_list = '<!-- wp:heading --><h2>Zahlungsanweisungen</h2><!-- /wp:heading -->'
            . fsfp_cli_workflow_overview('zahlung')
            . fsfp_cli_list_intro('zahlung')
            . fsfp_cli_payment_queue_links(home_url("/dashboard/{$slug}/zahlungsanweisungen/"))
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
                '<div class="fsfp-action-panel"><h3>AStA-Prüfung</h3><p>Rückfrage stellen oder Zahlung als ausgeführt markieren.</p><p>' . fsfp_cli_mailto_link('Fachschaft per E-Mail kontaktieren', "Rückfrage zu Zahlungsanweisung {$label}") . '</p></div>'
            )
            . fsfp_cli_members_access_block(["fs_{$slug}_finance", ...fsfp_cli_global_beschluss_edit_roles()], fsfp_cli_list_shortcode($types['zahlung'], 'zahlung', $slug, true, false, 'Bearbeiten / Einreichen / Stornieren'))
            . fsfp_cli_members_access_block(['asta_finance', 'asta_reviewer'], fsfp_cli_list_shortcode($types['zahlung'], 'zahlung', $slug, true, false, 'Rückfrage / Ausgeführt'));
        $zahlung_list_id = fsfp_cli_upsert_page('zahlungsanweisungen', 'Zahlungsanweisungen', $zahlung_list, $fachschaft_id);
        fsfp_cli_restrict_page_to_roles($zahlung_list_id, $fachschaft_nav_roles);
    
        $zahlung_detail_id = fsfp_cli_upsert_page('zahlungsanweisung-details', 'Zahlungsanweisung Details', fsfp_cli_detail_page_content($types['zahlung'], 'zahlung', home_url("/dashboard/{$slug}/zahlungsanweisungen/"), $slug, $types['zahlung']), $fachschaft_id);
        fsfp_cli_restrict_page_to_roles($zahlung_detail_id, $fachschaft_view_roles);
    
        $zahlung_create_fields = $forms_config['zahlung']['create'];
        $zahlung_finance_fields = $forms_config['zahlung']['finance_edit'];
        $zahlung_reviewer_fields = $forms_config['zahlung']['review_edit'];
        $zahlung_create_content = fsfp_cli_form_page(
            'zahlung',
            $types['zahlung'],
            $zahlung_create_fields,
            home_url("/dashboard/{$slug}/zahlungsanweisungen/?created=1"),
            'Zahlungsanweisung vorbereiten',
            fsfp_cli_payment_budget_guard($types['beschluss'], $types['zahlung'])
        );
        $zahlung_create_id = fsfp_cli_upsert_page('zahlungsanweisung-erstellen', 'Zahlungsanweisung erstellen', $zahlung_create_content, $fachschaft_id);
        fsfp_cli_restrict_page_to_roles($zahlung_create_id, $zahlung_create_roles);
        $zahlung_edit_id = fsfp_cli_upsert_page('zahlungsanweisung-bearbeiten', 'Zahlungsanweisung bearbeiten', fsfp_cli_role_gated_edit_form_page($types['zahlung'], [
            [
                'roles' => ["fs_{$slug}_finance", ...fsfp_cli_global_beschluss_edit_roles()],
                'title' => 'Fachschaft: bearbeiten, einreichen oder stornieren',
                'fields' => $zahlung_finance_fields,
                'label' => 'Änderungen speichern',
                'guard' => fsfp_cli_form_sanity_guard('zahlung'),
                'after' => fsfp_cli_payment_budget_guard($types['beschluss'], $types['zahlung']) . fsfp_cli_payment_type_lock_script(),
            ],
            [
                'roles' => ['asta_finance', 'asta_reviewer'],
                'title' => 'AStA: Rückfrage oder Ausführung',
                'fields' => $zahlung_reviewer_fields,
                'label' => 'Workflowstatus speichern',
                'after' => fsfp_cli_payment_type_lock_script(true),
            ],
        ], home_url("/dashboard/{$slug}/zahlungsanweisungen/")), $fachschaft_id);
        fsfp_cli_restrict_page_to_roles($zahlung_edit_id, $zahlung_edit_roles);
    
    }
    
    $global_beschluesse_id = fsfp_cli_upsert_page('beschluesse', 'Alle Beschlüsse', $global_beschluesse, $dashboard_id);
    fsfp_cli_restrict_page_to_roles($global_beschluesse_id, fsfp_cli_global_overview_roles());
    $global_zahlungen_id = fsfp_cli_upsert_page('zahlungsanweisungen', 'Alle Zahlungsanweisungen', $global_zahlungen, $dashboard_id);
    fsfp_cli_restrict_page_to_roles($global_zahlungen_id, fsfp_cli_global_overview_roles());
    $global_berichte_id = fsfp_cli_upsert_page('berichte', 'Berichte', $global_berichte, $dashboard_id);
    fsfp_cli_restrict_page_to_roles($global_berichte_id, fsfp_cli_global_overview_roles());
    $operations_id = fsfp_cli_upsert_page('betrieb', 'Betrieb', fsfp_cli_operations_page($fachschaften), $dashboard_id);
    fsfp_cli_restrict_page_to_roles($operations_id, ['administrator', 'portal_admin']);

    fsfp_cli_ensure_menu('Portal Navigation', $menu_items);
    fsfp_cli_ensure_block_navigation('Portal Navigation', $block_menu_items);
}
