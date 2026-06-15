<?php
/**
 * Setup-time helpers for FS-Finanzportal.
 */

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
        'Storniert' => 'Storniert',
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

function fsfp_cli_workflow_status_label_markup(string $status_field, array $labels): string
{
    $markup = '';
    foreach ($labels as $value => $label) {
        $markup .= '[if field="' . esc_attr($status_field) . '" value="' . esc_attr($value) . '"]' . esc_html($label) . '[/if]';
    }

    return $markup;
}

function fsfp_cli_return_to_url(string $url): string
{
    return rawurlencode($url);
}

function fsfp_cli_contextual_back_link_script(string $default_url): string
{
    return '<script>' . fsfp_cli_render_config_template('portal/assets/contextual-back-link.js', [
        'default_url_js' => esc_js($default_url),
    ]) . '</script>';
}

function fsfp_cli_contextual_form_redirect_script(string $default_url): string
{
    return '<script>' . fsfp_cli_render_config_template('portal/assets/contextual-form-redirect.js', [
        'default_url_js' => esc_js($default_url),
    ]) . '</script>';
}

function fsfp_cli_table_controls_script(): string
{
    return '<script>' . fsfp_cli_render_config_template('portal/assets/table-controls.js', []) . '</script>';
}

function fsfp_cli_payment_queue_links(string $base_url, bool $include_fachschaft = false): string
{
    $queues = [
        'Entwurf' => 'Entwürfe',
        'Eingereicht' => 'Eingereicht',
        'Rückfrage' => 'Rückfrage offen',
        'Ausgeführt' => 'Ausgeführt',
    ];
    $links = '';
    foreach ($queues as $status => $label) {
        $links .= '<a class="fsfp-queue-link" href="' . esc_url(add_query_arg('status', $status, $base_url)) . '">' . esc_html($label) . '</a>';
    }
    $hint = $include_fachschaft ? 'Status-Queues über alle Fachschaften.' : 'Status-Queues für diese Fachschaft.';

    return '<!-- wp:group --><div class="wp-block-group fsfp-queue-panel"><p>' . esc_html($hint) . '</p><div class="fsfp-queue-links">' . $links . '</div></div><!-- /wp:group -->';
}

function fsfp_cli_role_email(string $role, string $fallback = ''): string
{
    $users = get_users([
        'role' => $role,
        'number' => 1,
        'orderby' => 'ID',
        'order' => 'ASC',
    ]);

    if (!empty($users) && !empty($users[0]->user_email)) {
        return (string) $users[0]->user_email;
    }

    return $fallback !== '' ? $fallback : (string) get_option('admin_email');
}

function fsfp_cli_mailto_link(string $label, string $subject, string $body = '', string $recipient = ''): string
{
    $email = $recipient !== '' ? $recipient : (string) get_option('admin_email');
    $params = ['subject' => $subject];
    if ($body !== '') {
        $params['body'] = $body;
    }

    return '<a class="fsfp-mailto-link" href="mailto:' . esc_attr($email) . '?' . esc_attr(http_build_query($params, '', '&', PHP_QUERY_RFC3986)) . '">' . esc_html($label) . '</a>';
}

function fsfp_cli_workflow_normalization_summary_markup(): string
{
    $summary = get_option('fsfp_workflow_normalization_summary', []);
    if (!is_array($summary)) {
        $summary = [];
    }

    $rows = [
        [
            'key' => 'workflow-statuses-beschluss-legacy-mapped',
            'label' => 'Beschlussstatus legacy gemappt',
            'count' => (int) ($summary['workflow_statuses']['beschluss']['legacy_mapped'] ?? 0),
            'hint' => 'Alte Beschlussstatuswerte wurden auf die aktuelle Domäne abgebildet.',
        ],
        [
            'key' => 'workflow-statuses-beschluss-reset-to-draft',
            'label' => 'Beschlussstatus auf Entwurf gesetzt',
            'count' => (int) ($summary['workflow_statuses']['beschluss']['reset_to_draft'] ?? 0),
            'hint' => 'Ungültige Beschlussstatuswerte wurden auf Entwurf zurückgesetzt.',
        ],
        [
            'key' => 'workflow-statuses-zahlung-legacy-mapped',
            'label' => 'Zahlungsstatus legacy gemappt',
            'count' => (int) ($summary['workflow_statuses']['zahlung']['legacy_mapped'] ?? 0),
            'hint' => 'Alte Zahlungsstatuswerte wurden auf die aktuelle Domäne abgebildet.',
        ],
        [
            'key' => 'workflow-statuses-zahlung-reset-to-draft',
            'label' => 'Zahlungsstatus auf Entwurf gesetzt',
            'count' => (int) ($summary['workflow_statuses']['zahlung']['reset_to_draft'] ?? 0),
            'hint' => 'Ungültige Zahlungsstatuswerte wurden auf Entwurf zurückgesetzt.',
        ],
        [
            'key' => 'zahlungstyp-reset-to-standard',
            'label' => 'Zahlungstyp auf Standard gesetzt',
            'count' => (int) ($summary['zahlungstyp']['reset_to_standard'] ?? 0),
            'hint' => 'Ungültige Zahlungstypen wurden auf Standard normalisiert.',
        ],
        [
            'key' => 'beschluss-ref-cleared-for-vorkasse',
            'label' => 'Beschluss-Referenzen bei Vorkasse entfernt',
            'count' => (int) ($summary['beschluss_ref']['cleared_for_vorkasse'] ?? 0),
            'hint' => 'Vorkasse-Zahlungen wurden von Beschluss-Referenzen entkoppelt.',
        ],
    ];

    $total = array_sum(array_column($rows, 'count'));
    $row_markup = '';
    foreach ($rows as $row) {
        $row_markup .= '<tr data-ops-normalization-row="' . esc_attr($row['key']) . '">'
            . '<td>' . esc_html($row['label']) . '</td>'
            . '<td><span class="fsfp-ops-badge fsfp-ops-badge--ok" data-ops-normalization-count="' . esc_attr((string) $row['count']) . '">' . esc_html((string) $row['count']) . '</span></td>'
            . '<td>' . esc_html($row['hint']) . '</td>'
            . '</tr>';
    }

    return '<section class="fsfp-ops-section fsfp-ops-integrity">'
        . '<h3>Datenintegrität</h3>'
        . '<p class="fsfp-ops-integrity-summary">Normalisierungssumme <span class="fsfp-ops-badge fsfp-ops-badge--ok" data-ops-normalization-total="' . esc_attr((string) $total) . '">' . esc_html((string) $total) . '</span></p>'
        . '<table class="fsfp-table fsfp-ops-table fsfp-ops-integrity-table"><thead><tr><th>Prüfschritt</th><th>Count</th><th>Hinweis</th></tr></thead><tbody data-ops-normalization-summary>'
        . $row_markup
        . '</tbody></table>'
        . '</section>';
}

function fsfp_cli_budget_report_source(string $beschluss_post_type, string $zahlung_post_type, string $fachschaft_slug, string $fachschaft_label): string
{
    $beschluss_template = sanitize_key("fsfp-report-{$beschluss_post_type}-budgets");
    $zahlung_template = sanitize_key("fsfp-report-{$zahlung_post_type}-payments");

    fsfp_cli_upsert_pods_template(
        $beschluss_template,
        $beschluss_template,
        '<span class="fsfp-report-budget-row" data-fachschaft="' . esc_attr($fachschaft_slug) . '" data-fachschaft-label="' . esc_attr($fachschaft_label) . '" data-beschluss-id="{@ID}" data-budget-amount="{@betrag}"></span>'
    );
    fsfp_cli_upsert_pods_template(
        $zahlung_template,
        $zahlung_template,
        '<span class="fsfp-report-payment-row" data-fachschaft="' . esc_attr($fachschaft_slug) . '" data-payment-type="{@zahlungstyp}" data-beschluss-id="{@beschluss_ref.ID}" data-payment-amount="{@betrag}"></span>'
    );

    return '<!-- wp:shortcode -->' . "\n"
        . '[pods name="' . esc_attr($beschluss_post_type) . '" template="' . esc_attr($beschluss_template) . '" where="beschluss_status.meta_value = \'approved\'" expires="-1" limit="-1"]' . "\n"
        . '<!-- /wp:shortcode -->'
        . '<!-- wp:shortcode -->' . "\n"
        . '[pods name="' . esc_attr($zahlung_post_type) . '" template="' . esc_attr($zahlung_template) . '" expires="-1" limit="-1"]' . "\n"
        . '<!-- /wp:shortcode -->';
}

function fsfp_cli_budget_report(array $fachschaften): string
{
    $sources = '';
    foreach ($fachschaften as $fachschaft) {
        $slug = sanitize_key($fachschaft['slug']);
        $label = $fachschaft['label'];
        $types = fsfp_cli_workflow_types($slug);
        $sources .= fsfp_cli_budget_report_source($types['beschluss'], $types['zahlung'], $slug, $label);
    }

    return '<!-- wp:heading {"level":3} --><h3>Budgetübersicht</h3><!-- /wp:heading -->'
        . '<div class="fsfp-budget-report" data-fsfp-budget-report>'
        . '<div hidden>' . $sources . '</div>'
        . '<div class="fsfp-budget-summary" data-budget-report-summary hidden>'
        . '<div class="fsfp-budget-summary__item"><span class="fsfp-budget-summary__label">Gesamt beschlossen</span><strong data-budget-total>Wird berechnet...</strong></div>'
        . '<div class="fsfp-budget-summary__item"><span class="fsfp-budget-summary__label">Gesamt Zahlungsanweisungen</span><strong data-budget-spent>Wird berechnet...</strong></div>'
        . '<div class="fsfp-budget-summary__item"><span class="fsfp-budget-summary__label">Gesamt offen</span><strong data-budget-open>Wird berechnet...</strong></div>'
        . '</div>'
        . '<p class="fsfp-budget-report-empty" data-budget-report-empty hidden>Für die Budgetübersicht liegen noch keine genehmigten Beschlüsse oder Zahlungsanweisungen vor.</p>'
        . '<table class="fsfp-table" data-budget-report-table><thead><tr><th>Fachschaft</th><th>Beschlossen</th><th>Zahlungsanweisungen</th><th>Offen</th></tr></thead><tbody data-budget-report-body></tbody></table>'
        . '<script>(function(){var root=document.currentScript.closest("[data-fsfp-budget-report]");if(!root){return;}function parseAmount(text){var value=(text||"").replace(/ /g,"").replace(/[^0-9,.-]/g,"");if(!value){return 0;}var comma=value.lastIndexOf(","),dot=value.lastIndexOf(".");if(comma>dot){value=value.replace(/[.]/g,"").replace(",",".");}else if(dot>comma){value=value.replace(/,/g,"");}else{value=value.replace(",",".");}var parsed=parseFloat(value);return Number.isFinite(parsed)?parsed:0;}function money(v){return new Intl.NumberFormat("de-DE",{style:"currency",currency:"EUR"}).format(v);}var rows={},budgetByBeschluss={},totalBudget=0,totalSpent=0;root.querySelectorAll(".fsfp-report-budget-row").forEach(function(el){var f=el.dataset.fachschaft,label=el.dataset.fachschaftLabel||f,amount=parseAmount(el.dataset.budgetAmount);rows[f]=rows[f]||{label:label,budget:0,spent:0};rows[f].budget+=amount;budgetByBeschluss[el.dataset.beschlussId]=f;});root.querySelectorAll(".fsfp-report-payment-row").forEach(function(el){if(el.dataset.paymentType==="vorkasse"){return;}var f=budgetByBeschluss[el.dataset.beschlussId]||el.dataset.fachschaft;if(!rows[f]){return;}rows[f].spent+=parseAmount(el.dataset.paymentAmount);});var body=root.querySelector("[data-budget-report-body]"),summary=root.querySelector("[data-budget-report-summary]"),empty=root.querySelector("[data-budget-report-empty]"),table=root.querySelector("[data-budget-report-table]");if(!body){return;}var keys=Object.keys(rows).sort();if(!keys.length){body.innerHTML="";if(summary){summary.hidden=true;}if(empty){empty.hidden=false;}if(table){table.hidden=true;}return;}body.innerHTML=keys.map(function(f){var r=rows[f];totalBudget+=r.budget;totalSpent+=r.spent;return "<tr><td>"+r.label+"</td><td>"+money(r.budget)+"</td><td>"+money(r.spent)+"</td><td>"+money(r.budget-r.spent)+"</td></tr>";}).join("");if(summary){summary.hidden=false;var budgetEl=root.querySelector("[data-budget-total]"),spentEl=root.querySelector("[data-budget-spent]"),openEl=root.querySelector("[data-budget-open]");if(budgetEl){budgetEl.textContent=money(totalBudget);}if(spentEl){spentEl.textContent=money(totalSpent);}if(openEl){openEl.textContent=money(totalBudget-totalSpent);}}if(empty){empty.hidden=true;}if(table){table.hidden=false;}})();</script>'
        . '</div>';
}

function fsfp_cli_reporting_sources(string $beschluss_post_type, string $zahlung_post_type, string $fachschaft_slug, string $fachschaft_label): string
{
    $beschluss_template = sanitize_key("fsfp-report-{$beschluss_post_type}-rows");
    $zahlung_template = sanitize_key("fsfp-report-{$zahlung_post_type}-rows");

    fsfp_cli_upsert_pods_template(
        $beschluss_template,
        $beschluss_template,
        '<span class="fsfp-report-row fsfp-report-row--beschluss" data-kind="beschluss" data-fachschaft="' . esc_attr($fachschaft_slug) . '" data-fachschaft-label="' . esc_attr($fachschaft_label) . '" data-title="{@post_title}" data-status="{@beschluss_status}" data-report-date="{@beschlussdatum}" data-created-at="{@post_date}" data-amount="{@betrag}"></span>'
    );
    fsfp_cli_upsert_pods_template(
        $zahlung_template,
        $zahlung_template,
        '<span class="fsfp-report-row fsfp-report-row--zahlung" data-kind="zahlung" data-fachschaft="' . esc_attr($fachschaft_slug) . '" data-fachschaft-label="' . esc_attr($fachschaft_label) . '" data-title="{@post_title}" data-status="{@zahlungs_status}" data-report-date="{@submitted_at}" data-created-at="{@post_date}" data-amount="{@betrag}" data-payment-type="{@zahlungstyp}" data-submitted-at="{@submitted_at}" data-reviewed-at="{@reviewed_at}" data-executed-at="{@executed_at}" data-beschluss-id="{@beschluss_ref.ID}"></span>'
    );

    return '<!-- wp:shortcode -->' . "\n"
        . '[pods name="' . esc_attr($beschluss_post_type) . '" template="' . esc_attr($beschluss_template) . '" expires="-1" limit="-1" shortcodes="1"]' . "\n"
        . '<!-- /wp:shortcode -->' . "\n"
        . '<!-- wp:shortcode -->' . "\n"
        . '[pods name="' . esc_attr($zahlung_post_type) . '" template="' . esc_attr($zahlung_template) . '" expires="-1" limit="-1" shortcodes="1"]' . "\n"
        . '<!-- /wp:shortcode -->' . "\n";
}

function fsfp_cli_status_badge(string $field): string
{
    return '<span class="fsfp-status-badge">{@' . $field . '}</span>';
}

function fsfp_cli_list_shortcode(string $post_type, string $kind, string $fachschaft_slug, bool $include_edit_link = false, bool $hide_drafts = false, string $edit_label = 'Bearbeiten'): string
{
    $date_th = $kind === 'beschluss' ? '<th>Datum</th>' : '';
    $date_td = $kind === 'beschluss' ? '<td>{@beschlussdatum}</td>' : '';
    $type_th = $kind === 'zahlung' ? '<th>Typ</th>' : '';
    $type_td = $kind === 'zahlung'
        ? '<td><span class="fsfp-payment-type-badge">{@zahlungstyp}</span>[if field="zahlungstyp" value="vorkasse"] <span class="fsfp-payment-method-badge">{@vorkasse_method}</span>[/if]</td>'
        : '';
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
        . '<td>' . fsfp_cli_status_badge($status_field) . '</td>'
        . $type_td
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
    return '<!-- wp:group {"align":"wide"} --><div id="' . esc_attr($table_id) . '" class="wp-block-group alignwide fsfp-scoped-overview fsfp-table-wrap" data-fsfp-table="scoped" data-export-name="' . esc_attr($table_id) . '">' . "\n"
        . '<div class="fsfp-unified-controls">'
        . '<label>Suche <input type="search" data-scoped-search placeholder="Titel, ID, Betrag"></label>'
        . '<label>Status <select data-scoped-status>' . fsfp_cli_status_select_options($kind) . '</select></label>'
        . '<button type="button" class="fsfp-export-button" data-scoped-export>CSV exportieren</button>'
        . '</div>'
        . '<table class="fsfp-table fsfp-scoped-table"><thead><tr><th>ID</th><th>Titel</th><th>Status</th>' . $type_th . $date_th . '<th>Betrag</th><th>Aktionen</th></tr></thead><tbody data-scoped-body>'
        . '<!-- wp:shortcode -->' . "\n"
        . '[pods name="' . esc_attr($post_type) . '" template="' . esc_attr($template_slug) . '" expires="-1" limit="-1" shortcodes="1"]' . "\n"
        . '<!-- /wp:shortcode -->' . "\n"
        . '</tbody></table>'
        . '<p class="fsfp-unified-empty" data-scoped-empty hidden>Keine passenden Einträge gefunden.</p>'
        . '<div class="fsfp-unified-pagination"><button type="button" data-scoped-prev>Zurück</button><span data-scoped-page></span><button type="button" data-scoped-next>Weiter</button></div>'
        . fsfp_cli_table_controls_script()
        . '</div><!-- /wp:group -->';
}

function fsfp_cli_unified_overview_source(string $post_type, string $kind, string $fachschaft_slug, string $fachschaft_label): string
{
    $status_field = $kind === 'beschluss' ? 'beschluss_status' : 'zahlungs_status';
    $detail_slug = $kind === 'beschluss' ? 'beschluss-details' : 'zahlungsanweisung-details';
    $edit_slug = $kind === 'beschluss' ? '' : 'zahlungsanweisung-bearbeiten';
    $date_td = $kind === 'beschluss' ? '<td>{@beschlussdatum}</td>' : '';
    $type_td = $kind === 'zahlung'
        ? '<td><span class="fsfp-payment-type-badge">{@zahlungstyp}</span>[if field="zahlungstyp" value="vorkasse"] <span class="fsfp-payment-method-badge">{@vorkasse_method}</span>[/if]</td>'
        : '';
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
        . '<td>' . fsfp_cli_status_badge($status_field) . '</td>'
        . $type_td
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
    $type_th = $is_beschluss ? '' : '<th>Typ</th>';
    $summary = '';

    if (!$is_beschluss) {
        $summary = '<div class="fsfp-unified-summary fsfp-unified-payment-summary" data-unified-summary>'
            . '<div class="fsfp-unified-summary__item"><span class="fsfp-unified-summary__label">Gesamt</span><strong data-unified-summary-total>0</strong></div>'
            . '<div class="fsfp-unified-summary__item"><span class="fsfp-unified-summary__label">Entwurf</span><strong data-unified-summary-count="draft">0</strong></div>'
            . '<div class="fsfp-unified-summary__item"><span class="fsfp-unified-summary__label">Eingereicht</span><strong data-unified-summary-count="submitted">0</strong></div>'
            . '<div class="fsfp-unified-summary__item"><span class="fsfp-unified-summary__label">Rückfrage</span><strong data-unified-summary-count="correction_requested">0</strong></div>'
            . '<div class="fsfp-unified-summary__item"><span class="fsfp-unified-summary__label">Ausgeführt</span><strong data-unified-summary-count="executed">0</strong></div>'
            . '<div class="fsfp-unified-summary__item"><span class="fsfp-unified-summary__label">Storniert</span><strong data-unified-summary-count="cancelled">0</strong></div>'
            . '<p class="fsfp-unified-summary-empty" data-unified-summary-empty hidden>Keine passenden Zahlungsanweisungen gefunden.</p>'
            . '</div>';
    }

    $fachschaft_select = '<option value="">Alle Fachschaften</option>';
    $sources = '';
    foreach ($fachschaften as $fachschaft) {
        $slug = sanitize_key($fachschaft['slug']);
        $label = $fachschaft['label'];
        $types = fsfp_cli_workflow_types($slug);
        $fachschaft_select .= '<option value="' . esc_attr($slug) . '">' . esc_html($label) . '</option>';
        $sources .= fsfp_cli_unified_overview_source($types[$kind === 'beschluss' ? 'beschluss' : 'zahlung'], $kind, $slug, $label);
    }

    return '<!-- wp:heading --><h2>' . esc_html($title) . '</h2><!-- /wp:heading -->'
        . '<!-- wp:group {"align":"wide"} --><div id="' . esc_attr($table_id) . '" class="wp-block-group alignwide fsfp-unified-overview fsfp-table-wrap" data-fsfp-table="unified" data-export-name="' . esc_attr($table_id) . '">'
        . $summary
        . '<div class="fsfp-unified-controls">'
        . '<label>Suche <input type="search" data-unified-search placeholder="Titel, ID, Betrag"></label>'
        . '<label>Status <select data-unified-status>' . fsfp_cli_status_select_options($kind) . '</select></label>'
        . '<label>Fachschaft <select data-unified-fachschaft>' . $fachschaft_select . '</select></label>'
        . '<button type="button" class="fsfp-export-button" data-unified-export>CSV exportieren</button>'
        . '</div>'
        . '<table class="fsfp-table fsfp-unified-table"><thead><tr><th>Fachschaft</th><th>ID</th><th>Titel</th><th>Status</th>' . $type_th . $date_th . '<th>Betrag</th><th>Aktionen</th></tr></thead><tbody data-unified-body>' . $sources . '</tbody></table>'
        . '<p class="fsfp-unified-empty" data-unified-empty hidden>Keine passenden Einträge gefunden.</p>'
        . '<div class="fsfp-unified-pagination"><button type="button" data-unified-prev>Zurück</button><span data-unified-page></span><button type="button" data-unified-next>Weiter</button></div>'
        . fsfp_cli_table_controls_script()
        . '</div><!-- /wp:group -->';
}

function fsfp_cli_reporting_page(array $fachschaften): string
{
    $sources = '';
    foreach ($fachschaften as $fachschaft) {
        $slug = sanitize_key($fachschaft['slug']);
        $label = $fachschaft['label'];
        $types = fsfp_cli_workflow_types($slug);
        $sources .= fsfp_cli_reporting_sources($types['beschluss'], $types['zahlung'], $slug, $label);
    }

    return '<!-- wp:heading --><h2>Berichte</h2><!-- /wp:heading -->'
        . '<!-- wp:paragraph --><p>AStA-Berichte über Periodensummen, offene Arbeit, ausgeführte Zahlungen und Fachschaftssummen.</p><!-- /wp:paragraph -->'
        . '<div class="fsfp-reporting" data-fsfp-reporting>'
        . '<div hidden>' . $sources . '</div>'
        . '<div class="fsfp-report-summary" data-report-summary hidden>'
        . '<div class="fsfp-report-summary__item"><span class="fsfp-report-summary__label">Genehmigt</span><strong data-report-total-budget>0,00 EUR</strong></div>'
        . '<div class="fsfp-report-summary__item"><span class="fsfp-report-summary__label">Ausgeführt</span><strong data-report-total-executed>0,00 EUR</strong></div>'
        . '<div class="fsfp-report-summary__item"><span class="fsfp-report-summary__label">Offen</span><strong data-report-total-open>0,00 EUR</strong></div>'
        . '<div class="fsfp-report-summary__item"><span class="fsfp-report-summary__label">Offene Vorgänge</span><strong data-report-total-open-count>0</strong></div>'
        . '</div>'
        . '<p class="fsfp-report-empty" data-report-empty hidden>Für den Bericht liegen noch keine Workflowdaten vor.</p>'
        . '<section class="fsfp-report-section">'
        . '<h3>Periodenübersicht</h3>'
        . '<table class="fsfp-table fsfp-report-table"><thead><tr><th>Zeitraum</th><th>Beschlüsse</th><th>Zahlungsanweisungen</th><th>Ausgeführt</th><th>Offen</th></tr></thead><tbody data-report-period-body></tbody></table>'
        . '<p class="fsfp-report-section-empty" data-report-period-empty hidden>Keine Periodendaten vorhanden.</p>'
        . '</section>'
        . '<section class="fsfp-report-section">'
        . '<h3>Offene Arbeit</h3>'
        . '<table class="fsfp-table fsfp-report-table"><thead><tr><th>Status</th><th>Vorgänge</th><th>Betrag</th></tr></thead><tbody data-report-status-body></tbody></table>'
        . '<p class="fsfp-report-section-empty" data-report-status-empty hidden>Keine offenen Vorgänge vorhanden.</p>'
        . '</section>'
        . '<section class="fsfp-report-section">'
        . '<h3>Fachschaftsübersicht</h3>'
        . '<table class="fsfp-table fsfp-report-table"><thead><tr><th>Fachschaft</th><th>Beschlossen</th><th>Ausgeführt</th><th>Offen</th><th>Offene Vorgänge</th></tr></thead><tbody data-report-fachschaft-body></tbody></table>'
        . '<p class="fsfp-report-section-empty" data-report-fachschaft-empty hidden>Keine Fachschaftsdaten vorhanden.</p>'
        . '</section>'
        . '<script>(function(){'
        . 'var root=document.currentScript.closest("[data-fsfp-reporting]");if(!root){return;}'
        . 'function parseAmount(text){var value=(text||"").replace(/ /g,"").replace(/[^0-9,.-]/g,"");if(!value){return 0;}var comma=value.lastIndexOf(","),dot=value.lastIndexOf(".");if(comma>dot){value=value.replace(/[.]/g,"").replace(",",".");}else if(dot>comma){value=value.replace(/,/g,"");}else{value=value.replace(",",".");}var parsed=parseFloat(value);return Number.isFinite(parsed)?parsed:0;}'
        . 'function parseDate(value){var raw=(value||"").trim();if(!raw){return null;}var direct=new Date(raw);if(!Number.isNaN(direct.getTime())){return direct;}var match=raw.match(/^(\\d{4})-(\\d{2})(?:-(\\d{2}))?/);if(match){return new Date(Number(match[1]),Number(match[2])-1,Number(match[3]||1));}return null;}'
        . 'function money(value){return new Intl.NumberFormat("de-DE",{style:"currency",currency:"EUR"}).format(value);}'
        . 'function monthKey(date){return date.getFullYear()+"-"+String(date.getMonth()+1).padStart(2,"0");}'
        . 'function monthLabel(key){var parts=key.split("-");return new Intl.DateTimeFormat("de-DE",{month:"long",year:"numeric"}).format(new Date(Number(parts[0]),Number(parts[1])-1,1));}'
        . 'function formatCell(count,amount){return count+" · "+money(amount);}'
        . 'var rows=Array.from(root.querySelectorAll(".fsfp-report-row"));'
        . 'var summary=root.querySelector("[data-report-summary]");'
        . 'var summaryBudget=root.querySelector("[data-report-total-budget]");'
        . 'var summaryExecuted=root.querySelector("[data-report-total-executed]");'
        . 'var summaryOpen=root.querySelector("[data-report-total-open]");'
        . 'var summaryOpenCount=root.querySelector("[data-report-total-open-count]");'
        . 'var empty=root.querySelector("[data-report-empty]");'
        . 'var periodBody=root.querySelector("[data-report-period-body]");'
        . 'var statusBody=root.querySelector("[data-report-status-body]");'
        . 'var fachschaftBody=root.querySelector("[data-report-fachschaft-body]");'
        . 'var periodEmpty=root.querySelector("[data-report-period-empty]");'
        . 'var statusEmpty=root.querySelector("[data-report-status-empty]");'
        . 'var fachschaftEmpty=root.querySelector("[data-report-fachschaft-empty]");'
        . 'if(!periodBody||!statusBody||!fachschaftBody){return;}'
        . 'var periods={},fachschaften={},statusRows={"draft_beschluss":{label:"Beschluss Entwurf",count:0,amount:0},"draft":{label:"Entwurf",count:0,amount:0},"submitted":{label:"Eingereicht",count:0,amount:0},"correction_requested":{label:"Rückfrage",count:0,amount:0}};'
        . 'var totalBudget=0,totalExecuted=0,totalOpen=0,totalOpenCount=0;'
        . 'function ensurePeriod(key,label){if(!periods[key]){periods[key]={label:label,beschlussCount:0,beschlussAmount:0,paymentCount:0,paymentAmount:0,executedCount:0,executedAmount:0,openCount:0,openAmount:0};}return periods[key];}'
        . 'function ensureFachschaft(key,label){if(!fachschaften[key]){fachschaften[key]={label:label,budget:0,executed:0,open:0,openCount:0};}return fachschaften[key];}'
        . 'rows.forEach(function(row){var kind=row.dataset.kind||"";var status=row.dataset.status||"";var fachschaftKey=row.dataset.fachschaft||"unbekannt";var fachschaftLabel=row.dataset.fachschaftLabel||fachschaftKey;var amount=parseAmount(row.dataset.amount);var date=parseDate(row.dataset.reportDate||row.dataset.executedAt||row.dataset.submittedAt||row.dataset.reviewedAt||row.dataset.createdAt);var periodKey=date?monthKey(date):"unbekannt";var periodLabel=date?monthLabel(periodKey):"Ohne Datum";var period=ensurePeriod(periodKey,periodLabel);var fachschaft=ensureFachschaft(fachschaftKey,fachschaftLabel);if(kind==="beschluss"){period.beschlussCount+=1;if(status==="approved"){period.beschlussAmount+=amount;fachschaft.budget+=amount;totalBudget+=amount;}else if(status==="draft"){period.openCount+=1;period.openAmount+=amount;fachschaft.open+=amount;fachschaft.openCount+=1;totalOpen+=amount;totalOpenCount+=1;var draftBucket=statusRows.draft_beschluss;if(draftBucket){draftBucket.count+=1;draftBucket.amount+=amount;}}}else if(kind==="zahlung"){period.paymentCount+=1;period.paymentAmount+=amount;if(status==="executed"){period.executedCount+=1;period.executedAmount+=amount;fachschaft.executed+=amount;totalExecuted+=amount;}else if(status==="draft"||status==="submitted"||status==="correction_requested"){period.openCount+=1;period.openAmount+=amount;fachschaft.open+=amount;fachschaft.openCount+=1;totalOpen+=amount;totalOpenCount+=1;var bucket=statusRows[status];if(bucket){bucket.count+=1;bucket.amount+=amount;}}}if(kind==="zahlung"&&status==="cancelled"){period.paymentCount+=1;}});'
        . 'var periodKeys=Object.keys(periods).sort(function(a,b){if(a==="unbekannt"){return 1;}if(b==="unbekannt"){return -1;}return a>b?-1:1;});'
        . 'periodBody.innerHTML=periodKeys.map(function(key){var period=periods[key];return "<tr><td>"+period.label+"</td><td>"+formatCell(period.beschlussCount,period.beschlussAmount)+"</td><td>"+formatCell(period.paymentCount,period.paymentAmount)+"</td><td>"+formatCell(period.executedCount,period.executedAmount)+"</td><td>"+formatCell(period.openCount,period.openAmount)+"</td></tr>";}).join("");'
        . 'var statusKeys=Object.keys(statusRows);'
        . 'statusBody.innerHTML=statusKeys.map(function(key){var row=statusRows[key];return "<tr><td>"+row.label+"</td><td>"+row.count+"</td><td>"+money(row.amount)+"</td></tr>";}).join("");'
        . 'var fachschaftKeys=Object.keys(fachschaften).sort(function(a,b){return fachschaften[a].label.localeCompare(fachschaften[b].label,"de");});'
        . 'fachschaftBody.innerHTML=fachschaftKeys.map(function(key){var fachschaft=fachschaften[key];return "<tr><td>"+fachschaft.label+"</td><td>"+money(fachschaft.budget)+"</td><td>"+money(fachschaft.executed)+"</td><td>"+money(fachschaft.open)+"</td><td>"+fachschaft.openCount+"</td></tr>";}).join("");'
        . 'var hasRows=periodKeys.length>0||statusKeys.some(function(key){return statusRows[key].count>0;})||fachschaftKeys.length>0;'
        . 'if(summary){summary.hidden=!hasRows;}'
        . 'if(summaryBudget){summaryBudget.textContent=money(totalBudget);}'
        . 'if(summaryExecuted){summaryExecuted.textContent=money(totalExecuted);}'
        . 'if(summaryOpen){summaryOpen.textContent=money(totalOpen);}'
        . 'if(summaryOpenCount){summaryOpenCount.textContent=String(totalOpenCount);}'
        . 'if(empty){empty.hidden=hasRows;}'
        . 'if(periodEmpty){periodEmpty.hidden=periodKeys.length>0;}'
        . 'if(statusEmpty){statusEmpty.hidden=!statusKeys.some(function(key){return statusRows[key].count>0;});}'
        . 'if(fachschaftEmpty){fachschaftEmpty.hidden=fachschaftKeys.length>0;}'
        . '})();</script>'
        . '</div>';
}

function fsfp_cli_operations_page(array $fachschaften): string
{
    $members_settings = get_option('members_settings', []);
    if (!is_array($members_settings)) {
        $members_settings = [];
    }
    $hab_settings = get_option('hab_settings', []);
    if (!is_array($hab_settings)) {
        $hab_settings = [];
    }

    $active_plugins = (array) get_option('active_plugins', []);
    $meta_ledger_active = function_exists('is_plugin_active')
        ? is_plugin_active('meta-ledger/meta-ledger.php')
        : in_array('meta-ledger/meta-ledger.php', $active_plugins, true);

    $checks = [
        [
            'key' => 'members-content-permissions',
            'label' => 'Members content permissions',
            'ok' => !empty($members_settings['content_permissions']),
            'hint' => !empty($members_settings['content_permissions']) ? 'Aktiv' : 'Nicht aktiv',
        ],
        [
            'key' => 'members-rest-hide',
            'label' => 'Members REST shielding',
            'ok' => !empty($members_settings['hide_posts_rest_api']),
            'hint' => !empty($members_settings['hide_posts_rest_api']) ? 'REST-Ausgabe verborgen' : 'REST-Ausgabe sichtbar',
        ],
        [
            'key' => 'rda-redirect',
            'label' => 'Remove Dashboard Access redirect',
            'ok' => get_option('rda_access_switch') === 'capability'
                && get_option('rda_access_cap') === fsfp_cli_admin_edit_access_cap()
                && untrailingslashit((string) get_option('rda_redirect_url')) === untrailingslashit(home_url('/dashboard/')),
            'hint' => 'Capability gate and redirect URL',
        ],
        [
            'key' => 'admin-bar',
            'label' => 'Hide Admin Bar roles',
            'ok' => in_array('fs_portal_empty', (array) ($hab_settings['hab_userRoles'] ?? []), true),
            'hint' => 'Portal roles are registered',
        ],
        [
            'key' => 'meta-ledger',
            'label' => 'Meta Ledger retention',
            'ok' => (int) get_option('meta_ledger_retention_count') >= 200 && $meta_ledger_active,
            'hint' => $meta_ledger_active ? 'Retention 200+' : 'Plugin inactive',
        ],
    ];

    foreach ($fachschaften as $fachschaft) {
        $slug = sanitize_key($fachschaft['slug']);
        foreach (["fs_{$slug}_reader", "fs_{$slug}_finance"] as $role_name) {
            $checks[] = [
                'key' => 'hidden-admin-bar-' . $role_name,
                'label' => $role_name,
                'ok' => in_array($role_name, (array) ($hab_settings['hab_userRoles'] ?? []), true),
                'hint' => 'Hidden admin bar role sync',
            ];
        }
    }

    $page_checks = [
        '/dashboard/' => 'Dashboard',
        '/dashboard/beschluesse/' => 'Alle Beschlüsse',
        '/dashboard/zahlungsanweisungen/' => 'Alle Zahlungsanweisungen',
        '/dashboard/berichte/' => 'Berichte',
    ];
    foreach ($page_checks as $path => $label) {
        $page = get_page_by_path(trim($path, '/'), OBJECT, 'page');
        $checks[] = [
            'key' => 'page-' . sanitize_key(str_replace('/', '-', trim($path, '/'))),
            'label' => $label,
            'ok' => (bool) $page,
            'hint' => $path,
        ];
    }

    $check_rows = '';
    foreach ($checks as $check) {
        $check_rows .= '<tr data-ops-check="' . esc_attr($check['key']) . '">'
            . '<td>' . esc_html($check['label']) . '</td>'
            . '<td>' . ($check['ok'] ? '<span class="fsfp-ops-badge fsfp-ops-badge--ok">OK</span>' : '<span class="fsfp-ops-badge fsfp-ops-badge--warn">Fehlt</span>') . '</td>'
            . '<td>' . esc_html($check['hint']) . '</td>'
            . '</tr>';
    }

    $role_rows = '';
    foreach (['administrator', 'portal_admin'] as $role_name) {
        $role_rows .= '<tr data-ops-check="role-' . esc_attr($role_name) . '">'
            . '<td>' . esc_html($role_name) . '</td>'
            . '<td><span class="fsfp-ops-badge fsfp-ops-badge--ok">OK</span></td>'
            . '<td>Betriebsansicht freigegeben</td>'
            . '</tr>';
    }

    return '<!-- wp:heading --><h2>Betrieb</h2><!-- /wp:heading -->'
        . '<!-- wp:paragraph --><p>Setup- und Wiederherstellungsübersicht für Portal-Administratoren.</p><!-- /wp:paragraph -->'
        . '<div class="fsfp-ops" data-fsfp-ops>'
        . fsfp_cli_workflow_normalization_summary_markup()
        . '<section class="fsfp-ops-section">'
        . '<h3>Setup-Status</h3>'
        . '<table class="fsfp-table fsfp-ops-table"><thead><tr><th>Prüfschritt</th><th>Status</th><th>Hinweis</th></tr></thead><tbody>'
        . $check_rows
        . $role_rows
        . '</tbody></table>'
        . '</section>'
        . '<section class="fsfp-ops-section">'
        . '<h3>Wiederherstellung</h3>'
        . '<p>Bei Setup-Problemen zuerst die lokale Verifikation neu ausführen und dann den Dienstzustand prüfen.</p>'
        . '<ul class="fsfp-ops-list">'
        . '<li><code>./scripts/verify-setup.sh</code> zur erneuten Prüfung der Setup-Verkettung.</li>'
        . '<li><code>docker compose ps</code> für den Servicezustand.</li>'
        . '<li><code>docker compose logs wordpress</code> und <code>docker compose logs keycloak</code> für Fehlersuche.</li>'
        . '<li><code>docker compose down</code> und danach <code>docker compose up -d</code> für einen sauberen Neustart.</li>'
        . '</ul>'
        . '<p class="fsfp-ops-note">Backup und Restore bleiben aus der bestehenden Docker-/DB-Schicht zu handhaben; diese Seite hält nur die operationalen Prüfpunkte sichtbar.</p>'
        . '</section>'
        . '</div>';
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
        '<span class="fsfp-budget-source-row" data-payment-id="{@ID}" data-payment-type="{@zahlungstyp}" data-beschluss-id="{@beschluss_ref.ID}" data-payment-amount="{@betrag}"></span>' . "\n"
    );

    return '<div class="fsfp-budget-source" hidden>' . "\n"
        . '<!-- wp:shortcode -->' . "\n"
        . '[pods name="' . esc_attr($zahlung_post_type) . '" template="' . esc_attr($template_slug) . '" expires="-1" limit="-1"]' . "\n"
        . '<!-- /wp:shortcode -->' . "\n"
        . '</div>' . "\n";
}

function fsfp_cli_document_context_markup(string $status_field): string
{
    return '<section class="fsfp-action-panel fsfp-document-context">'
        . '<h4>Dokumentenkontext</h4>'
        . '<dl>'
        . '<dt>Erstellt am</dt><dd>{@post_date}</dd>'
        . '<dt>Zuletzt geändert am</dt><dd>{@post_modified}</dd>'
        . '<dt>Erstellt durch</dt><dd>{@post_author.display_name}</dd>'
        . '<dt>Status</dt><dd>{@' . $status_field . '}</dd>'
        . '</dl>'
        . '</section>';
}

function fsfp_cli_beschluss_budget_source_shortcode(string $beschluss_post_type): string
{
    $template_slug = sanitize_key("fsfp-{$beschluss_post_type}-budget-source");

    fsfp_cli_upsert_pods_template(
        $template_slug,
        $template_slug,
        '<span class="fsfp-beschluss-budget-row" data-beschluss-id="{@ID}" data-budget-amount="{@betrag}"></span>' . "\n"
    );

    return '<div class="fsfp-beschluss-budget-source" hidden>' . "\n"
        . '<!-- wp:shortcode -->' . "\n"
        . '[pods name="' . esc_attr($beschluss_post_type) . '" template="' . esc_attr($template_slug) . '" where="beschluss_status.meta_value = \'approved\'" expires="-1" limit="-1"]' . "\n"
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
        . 'root.querySelectorAll(".fsfp-budget-source-row").forEach(function(el){if(el.dataset.paymentType==="vorkasse"){return;}if(!current||el.dataset.beschlussId===current){paid+=parseAmount(el.dataset.paymentAmount||el.textContent);}});'
        . 'if(paidEl){paidEl.textContent=formatAmount(paid);}'
        . 'openEl.textContent=formatAmount(budget-paid);'
        . '})();</script>';
}

function fsfp_cli_payment_budget_guard(string $beschluss_post_type, string $zahlung_post_type): string
{
    return '<div class="fsfp-payment-budget-guard">'
        . fsfp_cli_beschluss_budget_source_shortcode($beschluss_post_type)
        . fsfp_cli_budget_source_shortcode($zahlung_post_type)
        . '<p class="fsfp-budget-warning" data-budget-warning hidden></p>'
        . '<script>(function(){'
        . 'var root=document.currentScript.closest(".fsfp-payment-form-scope");if(!root){return;}'
        . 'function parseAmount(text){var value=(text||"").replace(/ /g,"").replace(/[^0-9,.-]/g,"");if(!value){return 0;}var comma=value.lastIndexOf(","),dot=value.lastIndexOf(".");if(comma>dot){value=value.replace(/[.]/g,"").replace(",",".");}else if(dot>comma){value=value.replace(/,/g,"");}else{value=value.replace(",",".");}var parsed=parseFloat(value);return Number.isFinite(parsed)?parsed:0;}'
        . 'function formatAmount(value){return new Intl.NumberFormat("de-DE",{style:"currency",currency:"EUR"}).format(value);}'
        . 'var budgets={};root.querySelectorAll(".fsfp-beschluss-budget-row").forEach(function(row){budgets[row.dataset.beschlussId]=parseAmount(row.dataset.budgetAmount||row.textContent);});'
        . 'var params=new URLSearchParams(window.location.search);var currentId=params.get("id")||"";'
        . 'var spent={};root.querySelectorAll(".fsfp-budget-source-row").forEach(function(row){var id=row.dataset.beschlussId||"";if(row.dataset.paymentType==="vorkasse"||!id||row.dataset.paymentId===currentId){return;}spent[id]=(spent[id]||0)+parseAmount(row.dataset.paymentAmount||row.textContent);});'
        . 'function field(name){return root.querySelector(`[name="${name}"],[name="pods_field_${name}"],[name$="[${name}]"],[id$="-${name}"],[id$="-pods-field-${name.replace(/_/g,"-")}"],[id$="_${name}"]`);}'
        . 'function bind(){var amount=field("betrag");var beschluss=field("beschluss_ref");var zahlungstyp=field("zahlungstyp");var warning=root.querySelector("[data-budget-warning]");var submit=root.querySelector("button[type=submit],input[type=submit]");if(!amount||!beschluss||!warning||!submit){return false;}if(amount.dataset.fsfpBudgetBound==="1"){return true;}amount.dataset.fsfpBudgetBound="1";'
        . 'function selectedBeschluss(){var value=beschluss.value||"";if(value){return value;}var option=beschluss.options&&beschluss.selectedIndex>=0?beschluss.options[beschluss.selectedIndex]:null;return option?option.value:"";}'
        . 'function isVorkasse(){return zahlungstyp&&(zahlungstyp.value||"")==="vorkasse";}'
        . 'function validate(){if(isVorkasse()){warning.hidden=true;warning.textContent="";submit.disabled=false;return;}var id=selectedBeschluss();var requested=parseAmount(amount.value);var budget=budgets[id]||0;var used=spent[id]||0;var open=budget-used;var over=id&&requested>open+0.005;if(over){warning.hidden=false;warning.textContent="Der Betrag überschreitet das offene Budget dieses Beschlusses ("+formatAmount(open)+" verfügbar).";submit.disabled=true;}else{warning.hidden=true;warning.textContent="";submit.disabled=false;}}'
        . 'amount.addEventListener("input",validate);beschluss.addEventListener("change",validate);if(zahlungstyp){zahlungstyp.addEventListener("change",validate);}validate();return true;}'
        . 'if(!bind()){var observer=new MutationObserver(function(){if(bind()){observer.disconnect();}});observer.observe(root,{childList:true,subtree:true});setTimeout(bind,500);setTimeout(bind,1500);}'
        . '})();</script>'
        . '</div>';
}

function fsfp_cli_workflow_metadata_markup(string $post_type, string $kind): string
{
    $template_slug = sanitize_key("fsfp-{$post_type}-workflow-log");

    if ($kind === 'beschluss') {
        $status_markup = fsfp_cli_workflow_status_label_markup('beschluss_status', [
            'draft' => 'Entwurf',
            'approved' => 'Genehmigt',
            'rejected' => 'Abgelehnt',
        ]);
        fsfp_cli_upsert_pods_template(
            $template_slug,
            $template_slug,
            '<table class="fsfp-table fsfp-workflow-log">'
            . '<thead><tr><th>Schritt</th><th>Status</th><th>Datum</th><th>Person</th><th>Hinweis</th></tr></thead>'
            . '<tbody>'
            . '<tr><td>Erstellt</td><td>Entwurf</td><td>{@post_date}</td><td>{@post_author.display_name}</td><td></td></tr>'
            . '<tr><td>Entscheidung</td><td>' . $status_markup . '</td><td>{@decided_at}</td><td>{@decided_by}</td><td>{@decision_note}</td></tr>'
            . '</tbody></table>'
        );
    } else {
        $status_markup = fsfp_cli_workflow_status_label_markup('zahlungs_status', [
            'draft' => 'Entwurf',
            'submitted' => 'Eingereicht',
            'correction_requested' => 'Rückfrage',
            'cancelled' => 'Storniert',
            'executed' => 'Ausgeführt',
        ]);
        fsfp_cli_upsert_pods_template(
            $template_slug,
            $template_slug,
            '<table class="fsfp-table fsfp-workflow-log">'
            . '<thead><tr><th>Schritt</th><th>Status</th><th>Datum</th><th>Person</th><th>Hinweis</th></tr></thead>'
            . '<tbody>'
            . '<tr><td>Erstellt</td><td>Entwurf</td><td>{@post_date}</td><td>{@post_author.display_name}</td><td></td></tr>'
            . '<tr><td>Eingereicht</td><td>Eingereicht</td><td>{@submitted_at}</td><td></td><td>{@workflow_note}</td></tr>'
            . '<tr><td>Rückfrage</td><td>Rückfrage</td><td>{@clarification_requested_at}</td><td>{@clarification_requested_by}</td><td>{@clarification_request}</td></tr>'
            . '<tr><td>Antwort</td><td>Rückfrage beantwortet</td><td>{@clarification_answered_at}</td><td>{@clarification_answered_by}</td><td>{@clarification_response}</td></tr>'
            . '<tr><td>Geprüft</td><td>' . $status_markup . '</td><td>{@reviewed_at}</td><td>{@reviewed_by}</td><td>{@workflow_note}</td></tr>'
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
    $payment_type_markup = '';
    $payment_metadata_markup = '';
    $document_context_markup = fsfp_cli_document_context_markup($status_field);
    $clarification_markup = '';
    $follow_up_markup = '';
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
        $portal_admin_email = fsfp_cli_role_email('portal_admin');
        $asta_finance_email = fsfp_cli_role_email('asta_finance', $portal_admin_email);
        $asta_reviewer_email = fsfp_cli_role_email('asta_reviewer', $asta_finance_email);
        $fachschaft_finance_email = fsfp_cli_role_email("fs_{$fachschaft_slug}_finance", $portal_admin_email);
        $payment_type_markup = '<dt>Typ</dt><dd>[if field="zahlungstyp" value="vorkasse"]Zahlungsanweisung auf Vorkasse[/if][if field="zahlungstyp" value="vorkasse" compare="NOT IN"]Standard Zahlungsanweisung[/if]</dd>'
            . '[if field="zahlungstyp" value="vorkasse"]'
            . '<dt>Methode</dt><dd>{@vorkasse_method}</dd>'
            . '<dt>Begründung für Vorkasse</dt><dd>{@vorkasse_begruendung}</dd>'
            . '[if field="vorkasse_method" value="ueberweisung"]<dt>Empfänger Details / Kontoverbindung</dt><dd>{@empfaenger_details}</dd>[/if]'
            . '[/if]';
        $payment_metadata_markup = '<dt>Empfänger / Lieferant</dt><dd>{@vendor_name}</dd>'
            . '<dt>Rechnungsnummer / Referenz</dt><dd>{@invoice_number}</dd>'
            . '<dt>Rechnungsdatum</dt><dd>{@invoice_date}</dd>';
        $clarification_markup = '<section class="fsfp-clarification"><h4>Rückfrage</h4><dl>'
            . '<dt>Gestellt am</dt><dd>{@clarification_requested_at}</dd>'
            . '<dt>Gestellt durch</dt><dd>{@clarification_requested_by}</dd>'
            . '<dt>Rückfrage</dt><dd>{@clarification_request}</dd>'
            . '<dt>Beantwortet am</dt><dd>{@clarification_answered_at}</dd>'
            . '<dt>Beantwortet durch</dt><dd>{@clarification_answered_by}</dd>'
            . '<dt>Antwort</dt><dd>{@clarification_response}</dd>'
            . '</dl></section>';
        $follow_up_markup = '<section class="fsfp-action-panel fsfp-follow-up-panel">'
            . '<h4>Rückfrage, Benachrichtigung und Kontakt</h4>'
            . '<p>Wenn noch etwas offen ist, schreibe direkt an die Portal-Administration oder an das AStA-Team. Das hält die Rückfrage im normalen Prozess.</p>'
            . '<div class="fsfp-notification-grid">'
            . '<div class="fsfp-notification-item"><strong>Eingereicht</strong><span>AStA-Finanzteam</span><span>' . fsfp_cli_mailto_link('Entwurf öffnen', 'Zahlungsanweisung eingereicht: ' . $fachschaft_slug, 'Bitte prüfe die eingereichte Zahlungsanweisung.', $asta_finance_email) . '</span></div>'
            . '<div class="fsfp-notification-item"><strong>Rückfrage</strong><span>Fachschaft-Finance</span><span>' . fsfp_cli_mailto_link('Entwurf öffnen', 'Rückfrage zu Zahlungsanweisung: ' . $fachschaft_slug, 'Bitte beantworte die Rückfrage zur Zahlungsanweisung.', $fachschaft_finance_email) . '</span></div>'
            . '<div class="fsfp-notification-item"><strong>Antwort</strong><span>AStA-FSR Buchhaltung</span><span>' . fsfp_cli_mailto_link('Entwurf öffnen', 'Antwort auf Rückfrage: ' . $fachschaft_slug, 'Die Rückfrage wurde beantwortet. Bitte prüfe die Antwort.', $asta_reviewer_email) . '</span></div>'
            . '<div class="fsfp-notification-item"><strong>Ausgeführt</strong><span>Fachschaft-Finance</span><span>' . fsfp_cli_mailto_link('Entwurf öffnen', 'Zahlungsanweisung ausgeführt: ' . $fachschaft_slug, 'Die Zahlungsanweisung wurde ausgeführt.', $fachschaft_finance_email) . '</span></div>'
            . '<div class="fsfp-notification-item"><strong>Storniert</strong><span>Portal-Administration</span><span>' . fsfp_cli_mailto_link('Entwurf öffnen', 'Zahlungsanweisung storniert: ' . $fachschaft_slug, 'Die Zahlungsanweisung wurde vor Ausführung storniert.', $portal_admin_email) . '</span></div>'
            . '</div>'
            . '<p><strong>Nächster Schritt:</strong> ' . fsfp_cli_mailto_link('Portal-Administration kontaktieren', 'Rückfrage zu Zahlungsanweisung ' . $fachschaft_slug) . '</p>'
            . '<p>' . fsfp_cli_mailto_link('AStA-Finanzteam kontaktieren', 'AStA-Rückfrage zu Zahlungsanweisung ' . $fachschaft_slug) . '</p>'
            . '</section>';
        $reference_markup = '[if field="zahlungstyp" value="vorkasse" compare="NOT IN"]'
            . '<dt>Beschluss</dt><dd><a href="' . $beschluss_url . '">{@beschluss_ref.post_title}</a></dd>'
            . '<dt>Betrag Beschlossen</dt><dd data-budget-amount>{@beschluss_ref.betrag}</dd>'
            . '<dt>Betrag Offen</dt><dd data-open-budget>Wird berechnet...</dd>'
            . '<dd hidden data-current-beschluss-id="{@beschluss_ref.ID}"></dd>'
            . '[/if]';
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
        . $payment_type_markup
        . $amount_markup
        . '<dt>Beschreibung</dt><dd>{@' . $description_field . '}</dd>'
        . $payment_metadata_markup
        . $reference_markup
        . '<dt>Notizen</dt><dd>{@notes}</dd>'
        . '</dl>'
        . $document_context_markup
        . $follow_up_markup
        . $clarification_markup
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
        : ['Entwurf', 'Eingereicht', 'Rückfrage', 'Storniert', 'Ausgeführt'];

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
    return fsfp_cli_render_config_template('portal/templates/dashboard-card.html', [
        'title' => esc_html($title),
        'url' => esc_url($url),
        'button_label' => esc_html($button_label),
    ]);
}

function fsfp_cli_form_shortcode(string $post_type, string $fields, string $redirect): string
{
    return '<!-- wp:html -->' . "\n"
        . '[pods name="' . esc_attr($post_type) . '" form="true" fields="' . esc_attr($fields) . '" thank_you="' . esc_url($redirect) . '" label="Speichern"]' . "\n"
        . '<!-- /wp:html -->';
}

function fsfp_cli_form_sanity_guard(string $kind): string
{
    $rules = $kind === 'beschluss'
        ? [
            'post_title' => 'Bitte gib einen Titel an.',
            'betrag' => 'Der Betrag muss größer als 0 sein.',
            'beschlussdatum' => 'Das Beschlussdatum darf nicht in der Zukunft liegen.',
            'zweck_beschreibung' => 'Bitte beschreibe den Zweck etwas genauer.',
        ]
        : [
            'post_title' => 'Bitte gib einen Titel an.',
            'betrag' => 'Der Betrag muss größer als 0 sein.',
            'verwendungszweck' => 'Bitte beschreibe den Verwendungszweck etwas genauer.',
            'beschluss_ref' => 'Bitte wähle für Standard-Zahlungsanweisungen einen genehmigten Beschluss aus.',
            'vorkasse_method' => 'Bitte wähle eine Vorkasse-Methode aus.',
            'vorkasse_begruendung' => 'Bitte begründe, warum Vorkasse notwendig ist.',
            'empfaenger_details' => 'Bitte gib für Überweisungen die Empfänger- oder Kontodaten an.',
            'vendor_name' => 'Hinweis: Empfänger oder Lieferant fehlt.',
            'invoice_number' => 'Hinweis: Rechnungsnummer oder Referenz fehlt.',
            'invoice_date' => 'Hinweis: Rechnungsdatum fehlt oder liegt in der Zukunft.',
            'belege' => 'Hinweis: Es ist noch kein Beleg hochgeladen.',
        ];

    return '<div class="fsfp-form-errors" data-form-errors hidden></div>'
        . '<div class="fsfp-form-warnings" data-form-warnings hidden></div>'
        . '<script>(function(){'
        . 'var root=document.currentScript.closest(".fsfp-form-page,.fsfp-payment-form-scope");if(!root){return;}'
        . 'var messages=' . wp_json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
        . 'var warningFields={vendor_name:1,invoice_number:1,invoice_date:1,belege:1};'
        . 'function field(name){return root.querySelector(`[name="${name}"],[name="pods_field_${name}"],[name$="[${name}]"],[id$="-${name}"],[id$="-pods-field-${name.replace(/_/g,"-")}"],[id$="_${name}"]`);}'
        . 'function fieldWrap(el){return el?(el.closest(".pods-field")||el.closest(".pods-form-ui-row")||el.closest(".form-field")||el.parentElement):null;}'
        . 'function parseAmount(text){var value=(text||"").replace(/ /g,"").replace(/[^0-9,.-]/g,"");if(!value){return 0;}var comma=value.lastIndexOf(","),dot=value.lastIndexOf(".");if(comma>dot){value=value.replace(/[.]/g,"").replace(",",".");}else if(dot>comma){value=value.replace(/,/g,"");}else{value=value.replace(",",".");}var parsed=parseFloat(value);return Number.isFinite(parsed)?parsed:0;}'
        . 'function textValue(el){return el?(el.value||"").trim():"";}'
        . 'function isFutureDate(value){if(!value){return false;}var date=new Date(value+"T00:00:00");if(Number.isNaN(date.getTime())){return false;}var today=new Date();today.setHours(0,0,0,0);return date>today;}'
        . 'function setInvalid(el,invalid){if(!el){return;}el.classList.toggle("fsfp-field-invalid",invalid);}'
        . 'function isVorkasse(){var el=field("zahlungstyp");return el&&(el.value||"")==="vorkasse";}'
        . 'function isTransfer(){var el=field("vorkasse_method");return el&&(el.value||"")==="ueberweisung";}'
        . 'function hasFileValue(el){if(!el){return false;}if(el.files&&el.files.length){return true;}return !!textValue(el)||!!(fieldWrap(el)&&fieldWrap(el).querySelector(".pods-file, .pods-file-list, .pods-file-preview, a[href*=\"/wp-content/uploads/\"]"));}'
        . 'function syncVorkasseFields(){var vorkasse=isVorkasse();["vorkasse_method","vorkasse_begruendung"].forEach(function(name){var el=field(name),wrap=fieldWrap(el);if(wrap){wrap.hidden=!vorkasse;}if(el){el.required=vorkasse;}});var details=field("empfaenger_details"),detailsWrap=fieldWrap(details),showDetails=vorkasse&&isTransfer();if(detailsWrap){detailsWrap.hidden=!showDetails;}if(details){details.required=showDetails;}var beschluss=field("beschluss_ref"),beschlussWrap=fieldWrap(beschluss);if(beschlussWrap){beschlussWrap.hidden=vorkasse;}if(beschluss){beschluss.required=!vorkasse;if(vorkasse){beschluss.value="";}}}'
        . 'function validate(show){syncVorkasseFields();var errors=[],warnings=[];Object.keys(messages).forEach(function(name){var el=field(name);var invalid=false;if(name==="betrag"){invalid=parseAmount(textValue(el))<=0;}else if(name==="beschlussdatum"){invalid=!textValue(el)||isFutureDate(textValue(el));}else if(name==="invoice_date"){invalid=!textValue(el)||isFutureDate(textValue(el));}else if(name==="post_title"){invalid=textValue(el).length<3;}else if(name==="zweck_beschreibung"||name==="verwendungszweck"){invalid=textValue(el).length<10;}else if(name==="beschluss_ref"){invalid=!isVorkasse()&&!textValue(el);}else if(name==="vorkasse_method"){invalid=isVorkasse()&&!textValue(el);}else if(name==="vorkasse_begruendung"){invalid=isVorkasse()&&textValue(el).length<10;}else if(name==="empfaenger_details"){invalid=isVorkasse()&&isTransfer()&&textValue(el).length<5;}else if(name==="vendor_name"||name==="invoice_number"){invalid=textValue(el).length<2;}else if(name==="belege"){invalid=!hasFileValue(el);}setInvalid(el,invalid&&!warningFields[name]);if(invalid){(warningFields[name]?warnings:errors).push(messages[name]);}});var box=root.querySelector("[data-form-errors]");if(box){box.hidden=errors.length===0;box.innerHTML=errors.map(function(error){return "<p>"+error+"</p>";}).join("");}var warningBox=root.querySelector("[data-form-warnings]");if(warningBox){warningBox.hidden=warnings.length===0;warningBox.innerHTML=warnings.map(function(warning){return "<p>"+warning+"</p>";}).join("");}return errors.length===0;}'
        . 'root.addEventListener("input",function(){validate(false);});root.addEventListener("change",function(){validate(false);});'
        . 'function enhanceFields(){Object.keys(messages).forEach(function(name){var el=field(name);if(!el||el.dataset.fsfpSanityBound==="1"){return;}el.dataset.fsfpSanityBound="1";if(!warningFields[name]&&name!=="beschluss_ref"&&name!=="vorkasse_method"&&name!=="vorkasse_begruendung"&&name!=="empfaenger_details"){el.required=true;}if(name==="betrag"){el.setAttribute("min","0.01");}if(name==="post_title"){el.setAttribute("minlength","3");}if(name==="zweck_beschreibung"||name==="verwendungszweck"||name==="vorkasse_begruendung"){el.setAttribute("minlength","10");}});syncVorkasseFields();validate(false);}'
        . 'var form=root.querySelector("form");if(form){form.addEventListener("submit",function(event){enhanceFields();if(!validate(true)){event.preventDefault();event.stopImmediatePropagation();}},true);}'
        . 'enhanceFields();[250,750,1500,3000].forEach(function(delay){setTimeout(enhanceFields,delay);});'
        . '})();</script>';
}

function fsfp_cli_form_page(string $kind, string $post_type, string $fields, string $redirect, string $title, string $after = ''): string
{
    $payment_scope = $kind === 'zahlung' ? ' fsfp-payment-form-scope' : '';

    return fsfp_cli_render_config_template('portal/templates/form-shell.html', [
        'kind' => esc_attr($kind),
        'payment_scope' => $payment_scope,
        'title' => esc_html($title),
        'body' => fsfp_cli_form_shortcode($post_type, $fields, $redirect)
            . fsfp_cli_form_sanity_guard($kind)
            . $after,
    ]);
}

function fsfp_cli_payment_type_lock_script(bool $always = false): string
{
    return '<script>' . fsfp_cli_render_config_template('portal/assets/payment-type-lock.js', [
        'should_lock_js' => $always ? 'true' : '((status&&(status.value||"")!=="draft"))',
    ]) . '</script>';
}

function fsfp_cli_edit_form_page(string $post_type, string $fields, string $list_url): string
{
    return '<!-- wp:group --><div class="wp-block-group fsfp-edit-page fsfp-form-page fsfp-form-page--beschluss"><style>.fsfp-edit-page__form[hidden]{display:none}</style><!-- wp:paragraph --><p><a class="wp-block-button__link wp-element-button" data-fsfp-back-link href="' . esc_url($list_url) . '">Zur Liste zurück</a></p><!-- /wp:paragraph -->'
        . fsfp_cli_contextual_back_link_script($list_url)
        . fsfp_cli_contextual_form_redirect_script($list_url)
        . '<div class="fsfp-edit-page__notice"><!-- wp:paragraph --><p>Kein Datensatz ausgewählt.</p><!-- /wp:paragraph --></div><div class="fsfp-form-shell fsfp-edit-page__form" hidden><div class="fsfp-form-header"><h2>Beschluss bearbeiten</h2></div><div class="fsfp-form-body">'
        . '[pods name="' . esc_attr($post_type) . '" form="true" slug="{@get.id}" fields="' . esc_attr($fields) . '" thank_you="' . esc_url($list_url) . '" label="Änderungen speichern"]'
        . fsfp_cli_form_sanity_guard('beschluss')
        . '</div></div><script>(function(){var params=new URLSearchParams(window.location.search);var id=params.get("id");var form=document.querySelector(".fsfp-edit-page__form");var notice=document.querySelector(".fsfp-edit-page__notice");if(!form||!notice){return;}if(id&&id.length){form.hidden=false;notice.hidden=true;}else{form.hidden=true;notice.hidden=false;}})();</script></div><!-- /wp:group -->';
}

function fsfp_cli_role_gated_edit_form_page(string $post_type, array $forms, string $list_url): string
{
    $content = '<!-- wp:group --><div class="wp-block-group fsfp-edit-page"><style>.fsfp-edit-page__form[hidden]{display:none}</style><!-- wp:paragraph --><p>Öffne einen Datensatz über den Workflow-Link in der Liste. Diese Seite lädt einen vorhandenen Eintrag, wenn sie mit <code>?id=123</code> aufgerufen wird.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p><a class="wp-block-button__link wp-element-button" data-fsfp-back-link href="' . esc_url($list_url) . '">Zur Liste zurück</a></p><!-- /wp:paragraph -->'
        . fsfp_cli_contextual_back_link_script($list_url)
        . fsfp_cli_contextual_form_redirect_script($list_url)
        . '<div class="fsfp-edit-page__notice"><!-- wp:paragraph --><p>Kein Datensatz ausgewählt. Bitte nutze den Link in der Liste.</p><!-- /wp:paragraph --></div>';

    foreach ($forms as $form) {
        $content .= fsfp_cli_members_access_block(
            $form['roles'],
            '<div class="fsfp-action-panel fsfp-payment-form-scope fsfp-form-page fsfp-form-page--zahlung"><h3>' . esc_html($form['title']) . '</h3><div class="fsfp-form-shell fsfp-edit-page__form" hidden><div class="fsfp-form-body">'
            . '[pods name="' . esc_attr($post_type) . '" form="true" slug="{@get.id}" fields="' . esc_attr($form['fields']) . '" thank_you="' . esc_url($list_url) . '" label="' . esc_attr($form['label']) . '"]'
            . ($form['guard'] ?? '')
            . '</div></div>' . ($form['after'] ?? '') . '</div>'
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
