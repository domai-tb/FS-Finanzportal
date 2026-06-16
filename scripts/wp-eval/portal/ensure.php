<?php
/**
 * Main setup-time portal orchestration.
 */

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


fsfp_cli_configure_members_settings();
fsfp_cli_configure_meta_ledger($fachschaften);
fsfp_cli_ensure_portal_pages($fachschaften);
fsfp_cli_configure_portal_access_plugins($fachschaften);
fsfp_cli_apply_portal_custom_css();
fsfp_cli_seed_portal_demo($fachschaften);
fsfp_cli_publish_existing_workflow_posts($fachschaften);
fsfp_cli_normalize_workflow_statuses($fachschaften);

flush_rewrite_rules();

WP_CLI::success("Portal content configured.");
