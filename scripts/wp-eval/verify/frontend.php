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
    ) {
        fs_finanzportal_verify_fail('AStA global Beschluss overview must include a generated budget report.');
    }
    
    $global_beschluesse_reviewer_content = fs_finanzportal_render_page_as_user('demo-reviewer', $global_pages[0]);
    if (!str_contains($global_beschluesse_reviewer_content, 'Demo: Technik-Budget Sommerfest')
        || !str_contains($global_beschluesse_reviewer_content, 'fsfp-unified-table')
    ) {
        fs_finanzportal_verify_fail('AStA reviewer must see Beschluss entries in the unified overview table.');
    }
    
    $global_zahlungen_raw_content = $global_pages[1]->post_content;
    if (!str_contains($global_zahlungen_raw_content, 'fsfp-unified-zahlungen')) {
        fs_finanzportal_verify_fail('AStA global payment overview must include unified review actions for non-executed records.');
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
        || !str_contains($beschluss_workflow_template->post_content, '{@decided_at}')
        || !str_contains($beschluss_workflow_template->post_content, '{@decided_by}')
        || !str_contains($beschluss_workflow_template->post_content, '{@decision_note}')
    ) {
        fs_finanzportal_verify_fail('Beschluss workflow log template must render decision metadata.');
    }
    
    $related_zahlung_template = get_page_by_path('fsfp-za_informatik-related-to-beschluss', OBJECT, '_pods_template');
    if (!$related_zahlung_template
        || !str_contains($related_zahlung_template->post_content, '/dashboard/informatik/zahlungsanweisung-details/?id={@ID}')
        || !str_contains($related_zahlung_template->post_content, 'fsfp-related-zahlung-amount')
    ) {
        fs_finanzportal_verify_fail('Beschluss related Zahlungsanweisungen template must link to payment detail pages and expose amounts for budget calculation.');
    }
    
    $informatik_zahlungen_detail_page = $restricted_pages_by_fachschaft['informatik'][6];
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
    
    $zahlung_workflow_template = get_page_by_path('fsfp-za_informatik-workflow-log', OBJECT, '_pods_template');
    if (!$zahlung_workflow_template
        || !str_contains($zahlung_workflow_template->post_content, 'fsfp-workflow-log')
        || !str_contains($zahlung_workflow_template->post_content, '<td>Eingereicht</td>')
        || !str_contains($zahlung_workflow_template->post_content, '<td>Rückfrage</td>')
        || !str_contains($zahlung_workflow_template->post_content, '{@clarification_requested_at}')
        || !str_contains($zahlung_workflow_template->post_content, '{@clarification_response}')
        || !str_contains($zahlung_workflow_template->post_content, '<td>Geprüft</td>')
        || !str_contains($zahlung_workflow_template->post_content, '<td>Ausgeführt</td>')
        || !str_contains($zahlung_workflow_template->post_content, '{@submitted_at}')
        || !str_contains($zahlung_workflow_template->post_content, '{@reviewed_at}')
        || !str_contains($zahlung_workflow_template->post_content, '{@executed_at}')
        || !str_contains($zahlung_workflow_template->post_content, '{@workflow_note}')
    ) {
        fs_finanzportal_verify_fail('Payment workflow log template must render payment workflow metadata.');
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
