<?php
/**
 * Generated frontend workflow page verification.
 */

function fs_finanzportal_verify_frontend_workflows(array $restricted_pages_by_fachschaft, array $global_pages)
{
    $global_beschluesse_content = fs_finanzportal_render_page_as_user('demo-asta', $global_pages[0]);
    if (!str_contains($global_beschluesse_content, 'Demo: Technik-Budget Sommerfest')
        || !str_contains($global_beschluesse_content, 'Demo: Erstsemester-Material')
        || !str_contains($global_beschluesse_content, 'Demo: Literatur für Lesekreis')
        || !str_contains($global_beschluesse_content, 'data-fachschaft="informatik"')
        || !str_contains($global_beschluesse_content, 'data-fachschaft="maschinenbau"')
        || !str_contains($global_beschluesse_content, 'data-fachschaft="philosophie"')
    ) {
        fs_finanzportal_verify_fail('AStA global Beschluss overview must include source rows from multiple Fachschaften.');
    }
    if (!str_contains($global_pages[0]->post_content, 'fsfp-budget-report')
        || !str_contains($global_pages[0]->post_content, 'data-budget-report-body')
        || !str_contains($global_pages[0]->post_content, 'fsfp-budget-summary')
        || !str_contains($global_pages[0]->post_content, 'data-budget-total')
        || !str_contains($global_pages[0]->post_content, 'data-budget-spent')
        || !str_contains($global_pages[0]->post_content, 'data-budget-open')
        || !str_contains($global_pages[0]->post_content, 'fsfp-budget-report-empty')
    ) {
        fs_finanzportal_verify_fail('AStA global Beschluss overview must include a generated budget report with summary and empty-state markup.');
    }
    
    $global_beschluesse_reviewer_content = fs_finanzportal_render_page_as_user('demo-reviewer', $global_pages[0]);
    if (!str_contains($global_beschluesse_reviewer_content, 'Demo: Technik-Budget Sommerfest')
        || !str_contains($global_beschluesse_reviewer_content, 'fsfp-unified-table')
    ) {
        fs_finanzportal_verify_fail('AStA reviewer must see Beschluss entries in the unified overview table.');
    }

    $dashboard_page = fs_finanzportal_page_by_path('dashboard');
    $dashboard_rendered_as_admin = fs_finanzportal_render_page_as_role('administrator', $dashboard_page);
    $dashboard_rendered_as_asta = fs_finanzportal_render_page_as_user('demo-asta', $dashboard_page);
    if (!str_contains($dashboard_rendered_as_admin, 'Betrieb')
        || !str_contains($dashboard_rendered_as_admin, 'Betrieb öffnen')
        || !str_contains($dashboard_rendered_as_admin, 'Berichte öffnen')
    ) {
        fs_finanzportal_verify_fail('Administrator must see the Betrieb and reporting dashboard cards.');
    }
    if (str_contains($dashboard_rendered_as_asta, 'Betrieb öffnen')) {
        fs_finanzportal_verify_fail('AStA users must not see the Betrieb dashboard card.');
    }
    
    $global_zahlungen_raw_content = $global_pages[1]->post_content;
    if (!str_contains($global_zahlungen_raw_content, 'fsfp-unified-zahlungen')) {
        fs_finanzportal_verify_fail('AStA global payment overview must include unified review actions for non-executed records.');
    }
    if (!str_contains($global_zahlungen_raw_content, 'fsfp-unified-payment-summary')
        || !str_contains($global_zahlungen_raw_content, 'data-unified-summary-total')
        || !str_contains($global_zahlungen_raw_content, 'data-unified-summary-count="draft"')
        || !str_contains($global_zahlungen_raw_content, 'data-unified-summary-count="submitted"')
        || !str_contains($global_zahlungen_raw_content, 'data-unified-summary-count="correction_requested"')
        || !str_contains($global_zahlungen_raw_content, 'data-unified-summary-count="executed"')
        || !str_contains($global_zahlungen_raw_content, 'data-unified-summary-count="cancelled"')
        || !str_contains($global_zahlungen_raw_content, 'data-unified-summary-empty')
    ) {
        fs_finanzportal_verify_fail('AStA global payment overview must include a payment summary strip and empty-state markup.');
    }
    $global_berichte_raw_content = $global_pages[2]->post_content;
    $global_berichte_content = fs_finanzportal_render_page_as_user('demo-asta', $global_pages[2]);
    if (!str_contains($global_berichte_raw_content, 'fsfp-reporting')
        || !str_contains($global_berichte_raw_content, 'fsfp-report-summary')
        || !str_contains($global_berichte_raw_content, 'fsfp-report-context')
        || !str_contains($global_berichte_raw_content, 'fsfp-report-summary__note')
        || !str_contains($global_berichte_raw_content, 'data-report-period-body')
        || !str_contains($global_berichte_raw_content, 'data-report-status-body')
        || !str_contains($global_berichte_raw_content, 'data-report-fachschaft-body')
        || !str_contains($global_berichte_raw_content, 'data-report-total-budget')
        || !str_contains($global_berichte_raw_content, 'data-report-total-executed')
        || !str_contains($global_berichte_raw_content, 'data-report-total-open')
        || !str_contains($global_berichte_raw_content, 'data-report-total-rows')
        || !str_contains($global_berichte_raw_content, 'periodBody.innerHTML=periodKeys.map')
        || !str_contains($global_berichte_raw_content, 'statusBody.innerHTML=statusKeys.map')
        || !str_contains($global_berichte_raw_content, 'fachschaftBody.innerHTML=fachschaftKeys.map')
        || !str_contains($global_berichte_raw_content, 'summary.hidden=!hasRows')
        || !str_contains($global_berichte_raw_content, 'statusRows.draft_beschluss')
    ) {
        fs_finanzportal_verify_fail('AStA reporting page must include generated source rows, summary markup, and report tables.');
    }
    $report_beschluss_template = get_page_by_path('fsfp-report-b_informatik-rows', OBJECT, '_pods_template');
    $report_zahlung_template = get_page_by_path('fsfp-report-za_informatik-rows', OBJECT, '_pods_template');
    if (!$report_beschluss_template
        || !str_contains($report_beschluss_template->post_content, 'data-title="{@post_title}"')
        || !str_contains($report_beschluss_template->post_content, 'fsfp-report-row--beschluss')
        || !str_contains($report_beschluss_template->post_content, 'data-report-date="{@beschlussdatum}"')
        || !str_contains($report_beschluss_template->post_content, 'data-created-at="{@post_date}"')
        || !str_contains($report_beschluss_template->post_content, 'data-amount="{@betrag}"')
        || !$report_zahlung_template
        || !str_contains($report_zahlung_template->post_content, 'data-title="{@post_title}"')
        || !str_contains($report_zahlung_template->post_content, 'fsfp-report-row--zahlung')
        || !str_contains($report_zahlung_template->post_content, 'data-report-date="{@executed_at}"')
        || !str_contains($global_berichte_raw_content, 'kind==="zahlung"&&status==="executed"?row.dataset.executedAt:row.dataset.reportDate||row.dataset.submittedAt||row.dataset.reviewedAt||row.dataset.createdAt')
        || !str_contains($report_zahlung_template->post_content, 'data-created-at="{@post_date}"')
        || !str_contains($report_zahlung_template->post_content, 'data-payment-type="{@zahlungstyp}"')
        || !str_contains($report_zahlung_template->post_content, 'data-executed-at="{@executed_at}"')
    ) {
        fs_finanzportal_verify_fail('AStA reporting source templates must expose structured source rows for budgets and payments.');
    }
    if (!str_contains($global_berichte_content, 'Demo: Technik-Budget Sommerfest')
        || !str_contains($global_berichte_content, 'data-fachschaft="informatik"')
        || !str_contains($global_berichte_content, 'data-fachschaft="maschinenbau"')
        || !str_contains($global_berichte_content, 'data-fachschaft="philosophie"')
    ) {
        fs_finanzportal_verify_fail('AStA reporting page must render source rows for multiple Fachschaften.');
    }
    if (str_contains($global_berichte_raw_content, 'if(kind==="zahlung"&&status==="cancelled"){period.paymentCount+=1;}')) {
        fs_finanzportal_verify_fail('AStA reporting page must count cancelled payments only once.');
    }
    $operations_page = fs_finanzportal_page_by_path('dashboard/betrieb');
    $operations_raw_content = $operations_page->post_content;
    if (!str_contains($operations_raw_content, 'Datenintegrität')
        || !str_contains($operations_raw_content, 'data-ops-normalization-summary')
        || !str_contains($operations_raw_content, 'data-ops-normalization-row="workflow-statuses-beschluss-legacy-mapped"')
        || !str_contains($operations_raw_content, 'data-ops-normalization-total')
        || !str_contains($operations_raw_content, 'Setup-Status')
        || !str_contains($operations_raw_content, 'data-ops-check="members-content-permissions"')
        || !str_contains($operations_raw_content, 'Members content permissions')
        || !str_contains($operations_raw_content, 'data-ops-check="members-rest-hide"')
        || !str_contains($operations_raw_content, 'Members REST shielding')
        || !str_contains($operations_raw_content, 'data-ops-check="rda-redirect"')
        || !str_contains($operations_raw_content, 'Remove Dashboard Access redirect')
        || !str_contains($operations_raw_content, 'data-ops-check="admin-bar"')
        || !str_contains($operations_raw_content, 'Hide Admin Bar roles')
        || !str_contains($operations_raw_content, 'data-ops-check="meta-ledger"')
        || !str_contains($operations_raw_content, 'Meta Ledger retention')
        || !str_contains($operations_raw_content, 'data-ops-check="page-dashboard"')
        || !str_contains($operations_raw_content, 'data-ops-check="page-dashboard-beschluesse"')
        || !str_contains($operations_raw_content, 'data-ops-check="page-dashboard-zahlungsanweisungen"')
        || !str_contains($operations_raw_content, 'data-ops-check="page-dashboard-berichte"')
        || !str_contains($operations_raw_content, '>Dashboard<')
        || !str_contains($operations_raw_content, '>Alle Beschlüsse<')
        || !str_contains($operations_raw_content, '>Alle Zahlungsanweisungen<')
        || !str_contains($operations_raw_content, '>Berichte<')
        || !str_contains($operations_raw_content, 'Wiederherstellung')
        || !str_contains($operations_raw_content, './scripts/verify-setup.sh')
    ) {
        fs_finanzportal_verify_fail('The operations page must expose setup readiness checks and recovery guidance.');
    }
    $operations_rendered_as_admin = fs_finanzportal_render_page_as_role('administrator', $operations_page);
    if (!str_contains($operations_rendered_as_admin, 'Datenintegrität')
        || !str_contains($operations_rendered_as_admin, 'data-ops-normalization-summary')
        || !str_contains($operations_rendered_as_admin, 'Normalisierungssumme')
        || !str_contains($operations_rendered_as_admin, 'Setup-Status')
        || !str_contains($operations_rendered_as_admin, 'Wiederherstellung')
        || !str_contains($operations_rendered_as_admin, 'Members content permissions')
        || !str_contains($operations_rendered_as_admin, 'Remove Dashboard Access redirect')
        || !str_contains($operations_rendered_as_admin, 'Meta Ledger retention')
    ) {
        fs_finanzportal_verify_fail('The operations page must render the setup checklist for an administrator.');
    }
    foreach (array_keys($restricted_pages_by_fachschaft) as $slug) {
        foreach (["fs_{$slug}_reader", "fs_{$slug}_finance"] as $role_name) {
            if (!str_contains($operations_raw_content, 'data-ops-check="hidden-admin-bar-' . $role_name . '"')
                || !str_contains($operations_raw_content, '>' . $role_name . '<')
                || !str_contains($operations_raw_content, 'Hidden admin bar role sync')
            ) {
                fs_finanzportal_verify_fail('The operations page must expose hidden admin bar sync checks for each Fachschaft role.');
            }
        }
    }
    $portal_css = file_get_contents(__DIR__ . '/../../../wordpress/config/portal/assets/portal.css');
    $templates_php = file_get_contents(__DIR__ . '/../portal/templates.php');
    if ($portal_css === false
        || $templates_php === false
        || !str_contains($portal_css, '.fsfp-dashboard-page { display: grid; gap: 1.25rem; }')
        || !str_contains($portal_css, '.fsfp-dashboard-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }')
        || !str_contains($portal_css, '.fsfp-dashboard-card--feature {')
        || !str_contains($portal_css, '.fsfp-report-context { margin: 0 0 1rem;')
        || !str_contains($portal_css, '.fsfp-report-context__lead { margin: 0 0 0.55rem;')
        || !str_contains($portal_css, '.fsfp-report-summary__note { display: block;')
        || !str_contains($portal_css, '.fsfp-unified-summary { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr));')
        || !str_contains($portal_css, '.fsfp-unified-summary__item { min-width: 0; }')
        || !str_contains($portal_css, '.fsfp-unified-summary__label { display: block;')
        || !str_contains($portal_css, '.fsfp-unified-summary-empty { grid-column: 1 / -1;')
        || !str_contains($portal_css, '.fsfp-report-summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr));')
        || !str_contains($portal_css, '.fsfp-report-summary__item { min-width: 0; }')
        || !str_contains($portal_css, '.fsfp-report-summary__label { display: block;')
        || !str_contains($portal_css, '.fsfp-report-intro { max-width: 72ch;')
        || !str_contains($portal_css, '.fsfp-report-section { margin: 0 0 1.5rem; padding: 1rem 1.1rem;')
        || !str_contains($portal_css, '.fsfp-report-section-empty { margin: 1rem 0;')
        || !str_contains($portal_css, '.fsfp-ops-integrity { margin: 0 0 1.5rem; }')
        || !str_contains($portal_css, '.fsfp-ops-integrity-summary { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem;')
        || !str_contains($portal_css, '.fsfp-ops-integrity-table { margin-bottom: 0; }')
        || !str_contains($portal_css, '.fsfp-ops-page {')
        || !str_contains($portal_css, '.fsfp-ops-status {')
        || !str_contains($portal_css, '.fsfp-ops-check--ok td:nth-child(2) {')
        || !str_contains($portal_css, '.fsfp-ops-check--missing td:nth-child(2) {')
        || !str_contains($portal_css, '.fsfp-ops-recovery pre {')
        || !str_contains($portal_css, '.fsfp-document-context { margin: 1rem 0;')
        || !str_contains($portal_css, '.fsfp-document-context dl { display: grid; grid-template-columns: max-content minmax(0, 1fr);')
        || !str_contains($portal_css, '.fsfp-table tbody tr:nth-child(even) { background: #fcfdff; }')
        || !str_contains($portal_css, '.fsfp-table tbody tr:hover { background: #f1f5f9; }')
        || !str_contains($templates_php, 'function fsfp_cli_document_context_markup(string $status_field): string')
        || !str_contains($templates_php, 'function fsfp_cli_workflow_normalization_summary_markup(): string')
        || !str_contains($templates_php, 'function fsfp_cli_reporting_page(array $fachschaften): string')
        || !str_contains($templates_php, 'function fsfp_cli_reporting_sources(string $beschluss_post_type, string $zahlung_post_type, string $fachschaft_slug, string $fachschaft_label): string')
        || !str_contains($templates_php, 'function fsfp_cli_operations_page(array $fachschaften): string')
        || !str_contains($templates_php, 'data-ops-normalization-summary')
        || !str_contains($templates_php, 'data-ops-normalization-total')
        || !str_contains($templates_php, '\'key\' => \'hidden-admin-bar-\' . $role_name,')
        || !str_contains($templates_php, 'foreach (["fs_{$slug}_reader", "fs_{$slug}_finance"] as $role_name)')
        || !str_contains($templates_php, 'get_option(\'members_settings\'')
        || !str_contains($templates_php, 'get_option(\'hab_settings\'')
        || !str_contains($templates_php, 'get_option(\'meta_ledger_retention_count\'')
        || !str_contains($templates_php, 'get_page_by_path(trim($path, \'/\'), OBJECT, \'page\')')
        || !str_contains($templates_php, 'data-report-period-body')
        || !str_contains($templates_php, 'data-report-status-body')
        || !str_contains($templates_php, 'data-report-fachschaft-body')
        || !str_contains($templates_php, 'data-report-total-budget')
        || !str_contains($templates_php, 'data-report-total-executed')
        || !str_contains($templates_php, 'data-report-total-open')
        || !str_contains($templates_php, 'data-report-total-rows')
        || !str_contains($templates_php, 'fsfp-report-row--beschluss')
        || !str_contains($templates_php, 'fsfp-report-row--zahlung')
        || !str_contains($templates_php, '<section class="fsfp-action-panel fsfp-document-context">')
        || !str_contains($templates_php, '<h4>Dokumentenkontext</h4>')
        || !str_contains($templates_php, '<dt>Erstellt am</dt><dd>{@post_date}</dd>')
        || !str_contains($templates_php, '<dt>Zuletzt geändert am</dt><dd>{@post_modified}</dd>')
        || !str_contains($templates_php, '<dt>Erstellt durch</dt><dd>{@post_author.display_name}</dd>')
        || !str_contains($templates_php, '<dt>Status</dt><dd>{@')
        || !str_contains($templates_php, '</section>')
    ) {
        fs_finanzportal_verify_fail('Portal CSS must style the summary strips, reporting and operations pages, and compact document-context panel.');
    }
    
    $global_zahlung_template = get_page_by_path('fsfp-global-za_informatik-zahlung-rows', OBJECT, '_pods_template');
    if (!$global_zahlung_template
        || !str_contains($global_zahlung_template->post_content, 'Rückfrage / Ausgeführt')
        || !str_contains($global_zahlung_template->post_content, 'compare="NOT IN"')
    ) {
        fs_finanzportal_verify_fail('AStA global payment overview row template must include review actions for non-executed records.');
    }
    
    $global_zahlungen_reviewer_content = fs_finanzportal_render_page_as_user('demo-reviewer', $global_pages[1]);
    if (!str_contains($global_zahlungen_reviewer_content, 'fsfp-unified-table')) {
        fs_finanzportal_verify_fail('AStA reviewer must see the unified Zahlungsanweisungen overview table.');
    }
    
    $informatik_beschluesse_page = $restricted_pages_by_fachschaft['informatik'][1];
    $beschluss_edit_template = get_page_by_path('fsfp-b_informatik-bearbeiten-all', OBJECT, '_pods_template');
    if (!$beschluss_edit_template || !str_contains($beschluss_edit_template->post_content, '[if field="beschluss_status" value="draft"]')) {
        fs_finanzportal_verify_fail('Beschluss edit links must only be shown for draft records.');
    }
    
    $informatik_beschluss_edit_page = $restricted_pages_by_fachschaft['informatik'][4];
    if (!str_contains($informatik_beschluss_edit_page->post_content, 'decided_at')
        || !str_contains($informatik_beschluss_edit_page->post_content, 'decided_by')
        || !str_contains($informatik_beschluss_edit_page->post_content, 'decision_note')
        || !str_contains($informatik_beschluss_edit_page->post_content, '_pods_location')
        || !str_contains($informatik_beschluss_edit_page->post_content, 'form.dataset.location=absolute')
    ) {
        fs_finanzportal_verify_fail('Beschluss workflow form must expose decision date, actor, and note fields.');
    }
    
    $informatik_beschluss_create_page = $restricted_pages_by_fachschaft['informatik'][3];
    if (!str_contains($informatik_beschluss_create_page->post_content, 'fsfp-form-page--beschluss')
        || !str_contains($informatik_beschluss_create_page->post_content, 'fsfp-form-shell')
        || !str_contains($informatik_beschluss_create_page->post_content, 'Beschluss erfassen')
        || !str_contains($informatik_beschluss_create_page->post_content, 'data-form-errors')
        || !str_contains($informatik_beschluss_create_page->post_content, 'Der Betrag muss größer als 0 sein.')
        || !str_contains($informatik_beschluss_create_page->post_content, 'Das Beschlussdatum darf nicht in der Zukunft liegen.')
        || !str_contains($informatik_beschluss_create_page->post_content, 'pods_field_${name}')
        || !str_contains($informatik_beschluss_create_page->post_content, 'fsfpSanityBound')
        || !str_contains($informatik_beschluss_create_page->post_content, '[250,750,1500,3000]')
        || !str_contains($informatik_beschluss_create_page->post_content, 'stopImmediatePropagation')
        || !str_contains($informatik_beschluss_create_page->post_content, 'fsfp-field-invalid')
    ) {
        fs_finanzportal_verify_fail('Beschluss create page must use the styled form shell and basic sanity checks.');
    }
    
    $informatik_reader_content = fs_finanzportal_render_page_as_user('demo-informatik-reader', $informatik_beschluesse_page);
    if (!str_contains($informatik_reader_content, 'Demo: Technik-Budget Sommerfest')) {
        fs_finanzportal_verify_fail('Informatik reader must see Informatik Beschluss entries on the frontend list.');
    }
    if (str_contains($informatik_reader_content, 'wp-admin') || str_contains($informatik_reader_content, 'Erstellen') || str_contains($informatik_reader_content, 'Bearbeiten')) {
        fs_finanzportal_verify_fail('Informatik reader must not see create/edit controls on the frontend list.');
    }
    
    $informatik_finance_content = fs_finanzportal_render_page_as_user('demo-fachschaft', $informatik_beschluesse_page);
    if (!str_contains($informatik_finance_content, 'Demo: Technik-Budget Sommerfest')) {
        fs_finanzportal_verify_fail('Informatik finance must see Informatik Beschluss entries on the frontend list.');
    }
    if (str_contains($informatik_finance_content, 'wp-admin')
        || !str_contains($informatik_finance_content, 'return_to=%2Fdashboard%2Finformatik%2Fbeschluesse%2F')
        || !str_contains($informatik_finance_content, 'Neu erstellen')
        || !$beschluss_edit_template
        || !str_contains($beschluss_edit_template->post_content, '/dashboard/informatik/beschluss-bearbeiten/?id=')
        || !str_contains($beschluss_edit_template->post_content, 'Bearbeiten')
    ) {
        fs_finanzportal_verify_fail('Informatik finance must see frontend create/edit controls on the frontend list.');
    }
    
    $informatik_zahlungen_page = $restricted_pages_by_fachschaft['informatik'][5];
    $informatik_zahlungen_raw_content = $informatik_zahlungen_page->post_content;
    if (!str_contains($informatik_zahlungen_raw_content, 'Zahlungsanweisung vorbereiten')
        || !str_contains($informatik_zahlungen_raw_content, 'AStA-Prüfung')
        || !str_contains($informatik_zahlungen_raw_content, 'fsfp-queue-panel')
        || !str_contains($informatik_zahlungen_raw_content, 'Rückfrage offen')
        || !str_contains($informatik_zahlungen_raw_content, 'fsfp-status-flow')
    ) {
        fs_finanzportal_verify_fail('Payment list must contain finance and AStA workflow action controls.');
    }
    
    $informatik_beschluss_detail_page = $restricted_pages_by_fachschaft['informatik'][2];
    $informatik_zahlungen_detail_page = $restricted_pages_by_fachschaft['informatik'][6];
    if (!str_contains($informatik_beschluss_detail_page->post_content, 'Zugehörige Zahlungsanweisungen')
        || !str_contains($informatik_beschluss_detail_page->post_content, 'name="za_informatik"')
        || !str_contains($informatik_beschluss_detail_page->post_content, 'where="beschluss_ref.ID = {@get.id}"')
        || !str_contains($informatik_beschluss_detail_page->post_content, 'Betrag Beschlossen')
        || !str_contains($informatik_beschluss_detail_page->post_content, 'Betrag Offen')
        || !str_contains($informatik_beschluss_detail_page->post_content, 'data-open-budget')
        || !str_contains($informatik_beschluss_detail_page->post_content, 'Intl.NumberFormat')
        || !str_contains($informatik_beschluss_detail_page->post_content, '[^0-9,.-]')
        || !str_contains($informatik_beschluss_detail_page->post_content, 'var marker=root.querySelector("[data-current-beschluss-id]")')
    ) {
        fs_finanzportal_verify_fail('Beschluss detail page must list related Zahlungsanweisungen and calculate the open budget.');
    }
    foreach ([
        'Beschluss' => $informatik_beschluss_detail_page,
        'Zahlungsanweisung' => $informatik_zahlungen_detail_page,
    ] as $label => $page) {
        if (!str_contains($page->post_content, '<section class="fsfp-action-panel fsfp-document-context">')
            || !str_contains($page->post_content, '<h4>Dokumentenkontext</h4>')
            || !str_contains($page->post_content, '<dl>')
            || !str_contains($page->post_content, 'Erstellt am')
            || !str_contains($page->post_content, 'Zuletzt geändert am')
            || !str_contains($page->post_content, '{@post_date}')
            || !str_contains($page->post_content, '{@post_modified}')
            || !str_contains($page->post_content, '{@post_author.display_name}')
            || !str_contains($page->post_content, '</section>')
        ) {
            fs_finanzportal_verify_fail($label . ' detail page must include the compact document-context panel with creation and last-modified metadata.');
        }
    }
    if (!str_contains($informatik_beschluss_detail_page->post_content, 'Workflow-Log')
        || !str_contains($informatik_beschluss_detail_page->post_content, 'template="fsfp-b_informatik-workflow-log"')
        || !str_contains($informatik_beschluss_detail_page->post_content, 'data-fsfp-back-link')
        || !str_contains($informatik_beschluss_detail_page->post_content, 'params.get("return_to")')
        || !str_contains($informatik_beschluss_detail_page->post_content, 'function pathParts(path){return (path||"").split("/")')
        || str_contains($informatik_beschluss_detail_page->post_content, 'path.replace(/^/+|/+$/g')
        || str_contains($informatik_beschluss_detail_page->post_content, '[pods name="b_informatik" slug="{@get.id}"]<table')
        || str_contains($informatik_beschluss_detail_page->post_content, 'Meta Ledger protokolliert')
    ) {
        fs_finanzportal_verify_fail('Beschluss detail page must show a unified domain workflow log and contextual back link instead of static audit text.');
    }
    
    $beschluss_workflow_template = get_page_by_path('fsfp-b_informatik-workflow-log', OBJECT, '_pods_template');
    if (!$beschluss_workflow_template
        || !str_contains($beschluss_workflow_template->post_content, 'fsfp-workflow-log')
        || !str_contains($beschluss_workflow_template->post_content, '<td>Entscheidung</td>')
        || !str_contains($beschluss_workflow_template->post_content, '[if field="beschluss_status" value="draft"]Entwurf')
        || !str_contains($beschluss_workflow_template->post_content, '[if field="beschluss_status" value="approved"]Genehmigt')
        || !str_contains($beschluss_workflow_template->post_content, '[if field="beschluss_status" value="rejected"]Abgelehnt')
        || !str_contains($beschluss_workflow_template->post_content, '{@decided_at}')
        || !str_contains($beschluss_workflow_template->post_content, '{@decided_by}')
        || !str_contains($beschluss_workflow_template->post_content, '{@decision_note}')
        || str_contains($beschluss_workflow_template->post_content, '{@beschluss_status}')
    ) {
        fs_finanzportal_verify_fail('Beschluss workflow log template must render decision metadata with German status labels.');
    }
    
    $related_zahlung_template = get_page_by_path('fsfp-za_informatik-related-to-beschluss', OBJECT, '_pods_template');
    if (!$related_zahlung_template
        || !str_contains($related_zahlung_template->post_content, '/dashboard/informatik/zahlungsanweisung-details/?id={@ID}')
        || !str_contains($related_zahlung_template->post_content, 'fsfp-related-zahlung-amount')
    ) {
        fs_finanzportal_verify_fail('Beschluss related Zahlungsanweisungen template must link to payment detail pages and expose amounts for budget calculation.');
    }

    if (!str_contains($informatik_zahlungen_detail_page->post_content, 'Workflow-Log')
        || !str_contains($informatik_zahlungen_detail_page->post_content, 'template="fsfp-za_informatik-workflow-log"')
        || !str_contains($informatik_zahlungen_detail_page->post_content, 'data-fsfp-back-link')
        || !str_contains($informatik_zahlungen_detail_page->post_content, 'params.get("return_to")')
        || !str_contains($informatik_zahlungen_detail_page->post_content, 'function pathParts(path){return (path||"").split("/")')
        || str_contains($informatik_zahlungen_detail_page->post_content, 'path.replace(/^/+|/+$/g')
        || str_contains($informatik_zahlungen_detail_page->post_content, '[pods name="za_informatik" slug="{@get.id}"]<table')
        || str_contains($informatik_zahlungen_detail_page->post_content, 'Meta Ledger protokolliert')
    ) {
        fs_finanzportal_verify_fail('Payment detail page must show a unified domain workflow log and contextual back link instead of static audit text.');
    }
    if (!str_contains($informatik_zahlungen_detail_page->post_content, 'fsfp-follow-up-panel')
        || !str_contains($informatik_zahlungen_detail_page->post_content, 'Nächster Schritt:')
        || !str_contains($informatik_zahlungen_detail_page->post_content, 'Portal-Administration kontaktieren')
        || !str_contains($informatik_zahlungen_detail_page->post_content, 'AStA-Finanzteam kontaktieren')
        || !str_contains($informatik_zahlungen_detail_page->post_content, 'fsfp-mailto-link')
        || !str_contains($informatik_zahlungen_detail_page->post_content, 'mailto:')
        || !str_contains($informatik_zahlungen_detail_page->post_content, 'fsfp-notification-panel')
        || !str_contains($informatik_zahlungen_detail_page->post_content, 'fsfp-notification-grid')
        || !str_contains($informatik_zahlungen_detail_page->post_content, '<strong>Eingereicht</strong>')
        || !str_contains($informatik_zahlungen_detail_page->post_content, '<strong>Rückfrage</strong>')
        || !str_contains($informatik_zahlungen_detail_page->post_content, '<strong>Antwort</strong>')
        || !str_contains($informatik_zahlungen_detail_page->post_content, '<strong>Ausgeführt</strong>')
        || !str_contains($informatik_zahlungen_detail_page->post_content, '<strong>Storniert</strong>')
        || !str_contains($informatik_zahlungen_detail_page->post_content, 'Entwurf öffnen')
    ) {
        fs_finanzportal_verify_fail('Payment detail page must include structured follow-up and notification panels.');
    }

    $demo_vorkasse = get_page_by_path('demo-vorkasse-barkasse-sommerfest', OBJECT, 'za_informatik')
        ?: get_page_by_path('demo-vorkasse-informatik-barkasse-sommerfest', OBJECT, 'za_informatik');
    if (!$demo_vorkasse) {
        fs_finanzportal_verify_fail('Demo Vorkasse payment demo-vorkasse-barkasse-sommerfest is missing.');
    }
    $informatik_vorkasse_rendered = fs_finanzportal_render_page_as_user('demo-fachschaft', $informatik_zahlungen_detail_page, ['id' => $demo_vorkasse->ID]);
    if (!str_contains($informatik_vorkasse_rendered, 'Zahlungsanweisung auf Vorkasse')
        || !str_contains($informatik_vorkasse_rendered, 'Methode')
        || !str_contains($informatik_vorkasse_rendered, 'Begründung für Vorkasse')
        || !str_contains($informatik_vorkasse_rendered, 'fsfp-notification-panel')
        || !str_contains($informatik_vorkasse_rendered, 'fsfp-mailto-link')
        || str_contains($informatik_vorkasse_rendered, 'Betrag Beschlossen')
        || str_contains($informatik_vorkasse_rendered, 'data-current-beschluss-id')
        || str_contains($informatik_vorkasse_rendered, 'fsfp-budget-source')
    ) {
        fs_finanzportal_verify_fail('Vorkasse payment detail pages must render without Beschluss budget context but keep follow-up notifications.');
    }

    $demo_vorkasse_transfer = get_page_by_path('demo-vorkasse-ueberweisung-reservierung', OBJECT, 'za_maschinenbau')
        ?: get_page_by_path('demo-vorkasse-maschinenbau-reservierung', OBJECT, 'za_maschinenbau');
    if (!$demo_vorkasse_transfer) {
        fs_finanzportal_verify_fail('Demo transfer Vorkasse payment demo-vorkasse-ueberweisung-reservierung is missing.');
    }
    $maschinenbau_zahlungen_detail_page = $restricted_pages_by_fachschaft['maschinenbau'][6];
    $maschinenbau_vorkasse_rendered = fs_finanzportal_render_page_as_user('demo-maschinenbau-finance', $maschinenbau_zahlungen_detail_page, ['id' => $demo_vorkasse_transfer->ID]);
    if (!str_contains($maschinenbau_vorkasse_rendered, 'Zahlungsanweisung auf Vorkasse')
        || !str_contains($maschinenbau_vorkasse_rendered, 'Empfänger Details / Kontoverbindung')
        || !str_contains($maschinenbau_vorkasse_rendered, '{@empfaenger_details}')
        || !str_contains($maschinenbau_vorkasse_rendered, 'fsfp-notification-panel')
        || str_contains($maschinenbau_vorkasse_rendered, 'Betrag Beschlossen')
        || str_contains($maschinenbau_vorkasse_rendered, 'data-current-beschluss-id')
    ) {
        fs_finanzportal_verify_fail('Transfer Vorkasse payment detail pages must render recipient details and no Beschluss budget block.');
    }
    
    $zahlung_workflow_template = get_page_by_path('fsfp-za_informatik-workflow-log', OBJECT, '_pods_template');
    if (!$zahlung_workflow_template
        || !str_contains($zahlung_workflow_template->post_content, 'fsfp-workflow-log')
        || !str_contains($zahlung_workflow_template->post_content, '<td>Eingereicht</td>')
        || !str_contains($zahlung_workflow_template->post_content, '<td>Rückfrage</td>')
        || !str_contains($zahlung_workflow_template->post_content, '[if field="zahlungs_status" value="draft"]Entwurf')
        || !str_contains($zahlung_workflow_template->post_content, '[if field="zahlungs_status" value="submitted"]Eingereicht')
        || !str_contains($zahlung_workflow_template->post_content, '[if field="zahlungs_status" value="correction_requested"]Rückfrage')
        || !str_contains($zahlung_workflow_template->post_content, '[if field="zahlungs_status" value="cancelled"]Storniert')
        || !str_contains($zahlung_workflow_template->post_content, '[if field="zahlungs_status" value="executed"]Ausgeführt')
        || !str_contains($zahlung_workflow_template->post_content, '{@clarification_requested_at}')
        || !str_contains($zahlung_workflow_template->post_content, '{@clarification_response}')
        || !str_contains($zahlung_workflow_template->post_content, '<td>Geprüft</td>')
        || !str_contains($zahlung_workflow_template->post_content, '<td>Ausgeführt</td>')
        || !str_contains($zahlung_workflow_template->post_content, '{@submitted_at}')
        || !str_contains($zahlung_workflow_template->post_content, '{@reviewed_at}')
        || !str_contains($zahlung_workflow_template->post_content, '{@executed_at}')
        || !str_contains($zahlung_workflow_template->post_content, '{@workflow_note}')
        || str_contains($zahlung_workflow_template->post_content, '{@zahlungs_status}')
    ) {
        fs_finanzportal_verify_fail('Payment workflow log template must render payment workflow metadata with German status labels.');
    }
    if (!str_contains($informatik_zahlungen_detail_page->post_content, '/dashboard/informatik/beschluss-details/?id={@beschluss_ref.ID}')
        || !str_contains($informatik_zahlungen_detail_page->post_content, '{@beschluss_ref.post_title}')
        || !str_contains($informatik_zahlungen_detail_page->post_content, '{@beschluss_ref.betrag}')
        || !str_contains($informatik_zahlungen_detail_page->post_content, 'Zahlungsanweisung auf Vorkasse')
        || !str_contains($informatik_zahlungen_detail_page->post_content, '{@vorkasse_method}')
        || !str_contains($informatik_zahlungen_detail_page->post_content, '{@vorkasse_begruendung}')
        || !str_contains($informatik_zahlungen_detail_page->post_content, '{@empfaenger_details}')
        || !str_contains($informatik_zahlungen_detail_page->post_content, '{@vendor_name}')
        || !str_contains($informatik_zahlungen_detail_page->post_content, '{@invoice_number}')
        || !str_contains($informatik_zahlungen_detail_page->post_content, '{@invoice_date}')
        || !str_contains($informatik_zahlungen_detail_page->post_content, '{@clarification_request}')
        || !str_contains($informatik_zahlungen_detail_page->post_content, '{@clarification_response}')
        || !str_contains($informatik_zahlungen_detail_page->post_content, 'compare="NOT IN"]<dt>Beschluss')
        || !str_contains($informatik_zahlungen_detail_page->post_content, 'Betrag Zahlungsanweisung')
        || !str_contains($informatik_zahlungen_detail_page->post_content, 'Betrag Beschlossen')
        || !str_contains($informatik_zahlungen_detail_page->post_content, 'Betrag Offen')
        || !str_contains($informatik_zahlungen_detail_page->post_content, 'data-current-beschluss-id="{@beschluss_ref.ID}"')
        || !str_contains($informatik_zahlungen_detail_page->post_content, 'fsfp-budget-source')
    ) {
        fs_finanzportal_verify_fail('Payment detail page must link the related Beschluss and show calculated budget context.');
    }

    $render_payment_detail = static function (string $login, WP_Post $page, array $query): string {
        $previous_get = $_GET;
        $previous_request = $_REQUEST;

        foreach ($query as $key => $value) {
            $_GET[$key] = (string) $value;
            $_REQUEST[$key] = (string) $value;
        }

        try {
            return fs_finanzportal_render_page_as_user($login, $page);
        } finally {
            $_GET = $previous_get;
            $_REQUEST = $previous_request;
        }
    };

    $demo_vorkasse_barkasse = get_page_by_path('demo-vorkasse-barkasse-sommerfest', OBJECT, 'za_informatik')
        ?: get_page_by_path('demo-vorkasse-informatik-barkasse-sommerfest', OBJECT, 'za_informatik');
    if (!$demo_vorkasse_barkasse) {
        fs_finanzportal_verify_fail('Demo Vorkasse payment demo-vorkasse-barkasse-sommerfest is missing in za_informatik.');
    }

    $vorkasse_barkasse_rendered = $render_payment_detail('demo-fachschaft', $informatik_zahlungen_detail_page, [
        'id' => $demo_vorkasse_barkasse->ID,
    ]);
    if (!str_contains($vorkasse_barkasse_rendered, 'Zahlungsanweisung auf Vorkasse')
        || !str_contains($vorkasse_barkasse_rendered, 'Methode')
        || !str_contains($vorkasse_barkasse_rendered, 'Begründung für Vorkasse')
        || !str_contains($vorkasse_barkasse_rendered, 'fsfp-follow-up-panel')
        || !str_contains($vorkasse_barkasse_rendered, 'fsfp-notification-panel')
        || !str_contains($vorkasse_barkasse_rendered, 'fsfp-notification-grid')
    ) {
        fs_finanzportal_verify_fail('Rendered Vorkasse payment detail page must expose the Vorkasse fields and the follow-up notification panel.');
    }
    if (str_contains($vorkasse_barkasse_rendered, '/dashboard/informatik/beschluss-details/?id=')
        || str_contains($vorkasse_barkasse_rendered, 'Betrag Beschlossen')
        || str_contains($vorkasse_barkasse_rendered, 'data-current-beschluss-id')
        || str_contains($vorkasse_barkasse_rendered, 'fsfp-budget-source')
    ) {
        fs_finanzportal_verify_fail('Rendered Vorkasse payment detail page must not show the standard Beschluss link or budget block.');
    }

    $demo_vorkasse_transfer = get_page_by_path('demo-vorkasse-informatik-transportkosten', OBJECT, 'za_informatik')
        ?: get_page_by_path('demo-vorkasse-ueberweisung-reservierung', OBJECT, 'za_maschinenbau');
    if ($demo_vorkasse_transfer instanceof WP_Post) {
        $transfer_detail_page = $demo_vorkasse_transfer->post_type === 'za_informatik'
            ? $informatik_zahlungen_detail_page
            : $restricted_pages_by_fachschaft['maschinenbau'][6];
        $transfer_login = $demo_vorkasse_transfer->post_type === 'za_informatik'
            ? 'demo-fachschaft'
            : 'demo-maschinenbau-finance';
        $vorkasse_transfer_rendered = $render_payment_detail($transfer_login, $transfer_detail_page, [
            'id' => $demo_vorkasse_transfer->ID,
        ]);
        if (!str_contains($vorkasse_transfer_rendered, 'Empfänger Details / Kontoverbindung')) {
            fs_finanzportal_verify_fail('Rendered bank-transfer Vorkasse payment detail page must expose recipient account details.');
        }
    }
    
    $zahlung_budget_template = get_page_by_path('fsfp-za_informatik-budget-source', OBJECT, '_pods_template');
    if (!$zahlung_budget_template
        || !str_contains($zahlung_budget_template->post_content, 'data-payment-id="{@ID}"')
        || !str_contains($zahlung_budget_template->post_content, 'data-payment-type="{@zahlungstyp}"')
        || !str_contains($zahlung_budget_template->post_content, 'data-beschluss-id="{@beschluss_ref.ID}"')
        || !str_contains($zahlung_budget_template->post_content, 'data-payment-amount="{@betrag}"')
    ) {
        fs_finanzportal_verify_fail('Payment detail page must have a budget source template for related Zahlungsanweisungen.');
    }
    
    $beschluss_budget_template = get_page_by_path('fsfp-b_informatik-budget-source', OBJECT, '_pods_template');
    if (!$beschluss_budget_template
        || !str_contains($beschluss_budget_template->post_content, 'fsfp-beschluss-budget-row')
        || !str_contains($beschluss_budget_template->post_content, 'data-beschluss-id="{@ID}"')
        || !str_contains($beschluss_budget_template->post_content, 'data-budget-amount="{@betrag}"')
    ) {
        fs_finanzportal_verify_fail('Payment forms must have a Beschluss budget source template.');
    }
    
    $informatik_zahlung_create_page = $restricted_pages_by_fachschaft['informatik'][7];
    if (!str_contains($informatik_zahlung_create_page->post_content, 'fsfp-payment-form-scope')
        || !str_contains($informatik_zahlung_create_page->post_content, 'fsfp-form-page--zahlung')
        || !str_contains($informatik_zahlung_create_page->post_content, 'fsfp-form-shell')
        || !str_contains($informatik_zahlung_create_page->post_content, 'Zahlungsanweisung vorbereiten')
        || !str_contains($informatik_zahlung_create_page->post_content, 'zahlungstyp')
        || !str_contains($informatik_zahlung_create_page->post_content, 'vorkasse_method')
        || !str_contains($informatik_zahlung_create_page->post_content, 'vorkasse_begruendung')
        || !str_contains($informatik_zahlung_create_page->post_content, 'empfaenger_details')
        || !str_contains($informatik_zahlung_create_page->post_content, 'vendor_name')
        || !str_contains($informatik_zahlung_create_page->post_content, 'invoice_number')
        || !str_contains($informatik_zahlung_create_page->post_content, 'invoice_date')
        || !str_contains($informatik_zahlung_create_page->post_content, 'Hinweis: Es ist noch kein Beleg hochgeladen.')
        || !str_contains($informatik_zahlung_create_page->post_content, 'data-form-warnings')
        || !str_contains($informatik_zahlung_create_page->post_content, 'warningFields')
        || !str_contains($informatik_zahlung_create_page->post_content, 'fsfp-payment-budget-guard')
        || !str_contains($informatik_zahlung_create_page->post_content, 'fsfp-beschluss-budget-source')
        || !str_contains($informatik_zahlung_create_page->post_content, 'fsfp-budget-source')
        || !str_contains($informatik_zahlung_create_page->post_content, 'data-form-errors')
        || !str_contains($informatik_zahlung_create_page->post_content, 'Bitte wähle für Standard-Zahlungsanweisungen einen genehmigten Beschluss aus.')
        || !str_contains($informatik_zahlung_create_page->post_content, 'Bitte wähle eine Vorkasse-Methode aus.')
        || !str_contains($informatik_zahlung_create_page->post_content, 'syncVorkasseFields')
        || !str_contains($informatik_zahlung_create_page->post_content, 'beschluss.value=""')
        || !str_contains($informatik_zahlung_create_page->post_content, 'isVorkasse()')
        || !str_contains($informatik_zahlung_create_page->post_content, 'pods_field_${name}')
        || !str_contains($informatik_zahlung_create_page->post_content, 'fsfpSanityBound')
        || !str_contains($informatik_zahlung_create_page->post_content, '[250,750,1500,3000]')
        || !str_contains($informatik_zahlung_create_page->post_content, 'stopImmediatePropagation')
        || !str_contains($informatik_zahlung_create_page->post_content, 'data-budget-warning')
        || !str_contains($informatik_zahlung_create_page->post_content, 'Der Betrag überschreitet das offene Budget')
        || !str_contains($informatik_zahlung_create_page->post_content, 'submit.disabled=true')
        || !str_contains($informatik_zahlung_create_page->post_content, 'row.dataset.paymentType==="vorkasse"')
        || !str_contains($informatik_zahlung_create_page->post_content, 'where="beschluss_status.meta_value = \'approved\'"')
    ) {
        fs_finanzportal_verify_fail('Payment create page must include the frontend budget guard and approved Beschluss budget source.');
    }
    
    $zahlung_edit_template = get_page_by_path('fsfp-za_informatik-bearbeiteneinreichenstornieren-all', OBJECT, '_pods_template');
    if (!$zahlung_edit_template || !str_contains($zahlung_edit_template->post_content, 'compare="NOT IN"')) {
        fs_finanzportal_verify_fail('Payment edit links must be hidden for executed records where Pods templates support it.');
    }
    
    $informatik_zahlung_edit_page = $restricted_pages_by_fachschaft['informatik'][8];
    if (!str_contains($informatik_zahlung_edit_page->post_content, 'submitted_at')
        || !str_contains($informatik_zahlung_edit_page->post_content, 'reviewed_at')
        || !str_contains($informatik_zahlung_edit_page->post_content, 'clarification_requested_at')
        || !str_contains($informatik_zahlung_edit_page->post_content, 'clarification_response')
        || !str_contains($informatik_zahlung_edit_page->post_content, 'executed_at')
        || !str_contains($informatik_zahlung_edit_page->post_content, 'workflow_note')
    ) {
        fs_finanzportal_verify_fail('Payment workflow forms must expose workflow date and note fields.');
    }
    if (!str_contains($informatik_zahlung_edit_page->post_content, 'fsfp-payment-budget-guard')
        || !str_contains($informatik_zahlung_edit_page->post_content, 'row.dataset.paymentId===currentId')
        || !str_contains($informatik_zahlung_edit_page->post_content, '_pods_location')
        || !str_contains($informatik_zahlung_edit_page->post_content, 'form.dataset.location=absolute')
    ) {
        fs_finanzportal_verify_fail('Payment edit page must include the frontend budget guard, exclude the current payment from spent budget, and honor contextual return targets.');
    }
    
    $informatik_zahlungen_auditor_content = fs_finanzportal_render_page_as_user('demo-auditor', $informatik_zahlungen_page);
    if (str_contains($informatik_zahlungen_auditor_content, 'Neu erstellen')
        || str_contains($informatik_zahlungen_auditor_content, 'Bearbeiten')
        || str_contains($informatik_zahlungen_auditor_content, 'Rückfrage / Ausgeführt')
    ) {
        fs_finanzportal_verify_fail('Auditor must not see payment create/edit/action controls.');
    }
}
