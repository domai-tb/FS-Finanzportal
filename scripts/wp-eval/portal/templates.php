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
        . '<td>{@' . $status_field . '}</td>'
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
        . '<table class="fsfp-table fsfp-scoped-table"><thead><tr><th>ID</th><th>Titel</th><th>Status</th>' . $type_th . $date_th . '<th>Betrag</th><th>Aktionen</th></tr></thead><tbody data-scoped-body>'
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
        . '<td>{@' . $status_field . '}</td>'
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
        . '<table class="fsfp-table fsfp-unified-table"><thead><tr><th>Fachschaft</th><th>ID</th><th>Titel</th><th>Status</th>' . $type_th . $date_th . '<th>Betrag</th><th>Aktionen</th></tr></thead><tbody data-unified-body>' . $sources . '</tbody></table>'
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
        '<span class="fsfp-budget-source-row" data-payment-id="{@ID}" data-payment-type="{@zahlungstyp}" data-beschluss-id="{@beschluss_ref.ID}" data-payment-amount="{@betrag}"></span>' . "\n"
    );

    return '<div class="fsfp-budget-source" hidden>' . "\n"
        . '<!-- wp:shortcode -->' . "\n"
        . '[pods name="' . esc_attr($zahlung_post_type) . '" template="' . esc_attr($template_slug) . '" expires="-1" limit="-1"]' . "\n"
        . '<!-- /wp:shortcode -->' . "\n"
        . '</div>' . "\n";
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
    $payment_type_markup = '';
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
        $payment_type_markup = '<dt>Typ</dt><dd>[if field="zahlungstyp" value="vorkasse"]Zahlungsanweisung auf Vorkasse[/if][if field="zahlungstyp" value="vorkasse" compare="NOT IN"]Standard Zahlungsanweisung[/if]</dd>'
            . '[if field="zahlungstyp" value="vorkasse"]'
            . '<dt>Methode</dt><dd>{@vorkasse_method}</dd>'
            . '<dt>Begründung für Vorkasse</dt><dd>{@vorkasse_begruendung}</dd>'
            . '[if field="vorkasse_method" value="ueberweisung"]<dt>Empfänger Details / Kontoverbindung</dt><dd>{@empfaenger_details}</dd>[/if]'
            . '[/if]';
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
        ];

    return '<div class="fsfp-form-errors" data-form-errors hidden></div>'
        . '<script>(function(){'
        . 'var root=document.currentScript.closest(".fsfp-form-page,.fsfp-payment-form-scope");if(!root){return;}'
        . 'var messages=' . wp_json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
        . 'function field(name){return root.querySelector(`[name="${name}"],[name="pods_field_${name}"],[name$="[${name}]"],[id$="-${name}"],[id$="-pods-field-${name.replace(/_/g,"-")}"],[id$="_${name}"]`);}'
        . 'function fieldWrap(el){return el?(el.closest(".pods-field")||el.closest(".pods-form-ui-row")||el.closest(".form-field")||el.parentElement):null;}'
        . 'function parseAmount(text){var value=(text||"").replace(/ /g,"").replace(/[^0-9,.-]/g,"");if(!value){return 0;}var comma=value.lastIndexOf(","),dot=value.lastIndexOf(".");if(comma>dot){value=value.replace(/[.]/g,"").replace(",",".");}else if(dot>comma){value=value.replace(/,/g,"");}else{value=value.replace(",",".");}var parsed=parseFloat(value);return Number.isFinite(parsed)?parsed:0;}'
        . 'function textValue(el){return el?(el.value||"").trim():"";}'
        . 'function isFutureDate(value){if(!value){return false;}var date=new Date(value+"T00:00:00");if(Number.isNaN(date.getTime())){return false;}var today=new Date();today.setHours(0,0,0,0);return date>today;}'
        . 'function setInvalid(el,invalid){if(!el){return;}el.classList.toggle("fsfp-field-invalid",invalid);}'
        . 'function isVorkasse(){var el=field("zahlungstyp");return el&&(el.value||"")==="vorkasse";}'
        . 'function isTransfer(){var el=field("vorkasse_method");return el&&(el.value||"")==="ueberweisung";}'
        . 'function syncVorkasseFields(){var vorkasse=isVorkasse();["vorkasse_method","vorkasse_begruendung"].forEach(function(name){var el=field(name),wrap=fieldWrap(el);if(wrap){wrap.hidden=!vorkasse;}if(el){el.required=vorkasse;}});var details=field("empfaenger_details"),detailsWrap=fieldWrap(details),showDetails=vorkasse&&isTransfer();if(detailsWrap){detailsWrap.hidden=!showDetails;}if(details){details.required=showDetails;}var beschluss=field("beschluss_ref"),beschlussWrap=fieldWrap(beschluss);if(beschlussWrap){beschlussWrap.hidden=vorkasse;}if(beschluss){beschluss.required=!vorkasse;if(vorkasse){beschluss.value="";}}}'
        . 'function validate(show){syncVorkasseFields();var errors=[];Object.keys(messages).forEach(function(name){var el=field(name);var invalid=false;if(name==="betrag"){invalid=parseAmount(textValue(el))<=0;}else if(name==="beschlussdatum"){invalid=!textValue(el)||isFutureDate(textValue(el));}else if(name==="post_title"){invalid=textValue(el).length<3;}else if(name==="zweck_beschreibung"||name==="verwendungszweck"){invalid=textValue(el).length<10;}else if(name==="beschluss_ref"){invalid=!isVorkasse()&&!textValue(el);}else if(name==="vorkasse_method"){invalid=isVorkasse()&&!textValue(el);}else if(name==="vorkasse_begruendung"){invalid=isVorkasse()&&textValue(el).length<10;}else if(name==="empfaenger_details"){invalid=isVorkasse()&&isTransfer()&&textValue(el).length<5;}setInvalid(el,invalid);if(invalid){errors.push(messages[name]);}});var box=root.querySelector("[data-form-errors]");if(box){box.hidden=errors.length===0;box.innerHTML=errors.map(function(error){return "<p>"+error+"</p>";}).join("");}return errors.length===0;}'
        . 'root.addEventListener("input",function(){validate(false);});root.addEventListener("change",function(){validate(false);});'
        . 'function enhanceFields(){Object.keys(messages).forEach(function(name){var el=field(name);if(!el||el.dataset.fsfpSanityBound==="1"){return;}el.dataset.fsfpSanityBound="1";if(name!=="beschluss_ref"&&name!=="vorkasse_method"&&name!=="vorkasse_begruendung"&&name!=="empfaenger_details"){el.required=true;}if(name==="betrag"){el.setAttribute("min","0.01");}if(name==="post_title"){el.setAttribute("minlength","3");}if(name==="zweck_beschreibung"||name==="verwendungszweck"||name==="vorkasse_begruendung"){el.setAttribute("minlength","10");}});syncVorkasseFields();validate(false);}'
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
