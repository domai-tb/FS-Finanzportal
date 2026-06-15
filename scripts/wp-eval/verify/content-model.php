<?php
/**
 * Content model, Pods field, role, and capability verification.
 */

function fs_finanzportal_verify_content_model(): array
{
    $pods_api = pods_api();
    $fachschaften = fs_finanzportal_load_fachschaften();
    $all_read_caps = [];
    $all_edit_caps = [];
    $all_beschluss_read_caps = [];
    $all_beschluss_write_caps = [];
    $all_zahlung_edit_caps = [];
    $all_workflow_post_types = [];
    
    foreach ($fachschaften as $fachschaft) {
        $slug = sanitize_key($fachschaft['slug']);
        $types = fs_finanzportal_workflow_types($slug);
        $all_workflow_post_types = array_merge($all_workflow_post_types, array_values($types));
    
        foreach ($types as $kind => $post_type) {
            if (!post_type_exists($post_type)) {
                fs_finanzportal_verify_fail("Post type {$post_type} is not registered.");
            }
    
            $capability_type = fs_finanzportal_capability_type($post_type);
            $object = get_post_type_object($post_type);
            $plural = "{$capability_type}s";
    
            if (!$object || ($object->cap->edit_posts ?? '') !== "edit_{$plural}") {
                fs_finanzportal_verify_fail("Post type {$post_type} does not use custom capability type {$capability_type}.");
            }
    
            $required_fields = $kind === 'beschluss'
                ? ['fachschaft', 'beschlussdatum', 'betrag', 'zweck_beschreibung', 'beschluss_status', 'decided_at', 'decided_by', 'decision_note', 'belege', 'notes']
                : ['fachschaft', 'zahlungstyp', 'betrag', 'verwendungszweck', 'vorkasse_method', 'vorkasse_begruendung', 'empfaenger_details', 'zahlungs_status', 'submitted_at', 'reviewed_at', 'reviewed_by', 'clarification_requested_at', 'clarification_requested_by', 'clarification_request', 'clarification_answered_at', 'clarification_answered_by', 'clarification_response', 'executed_at', 'executed_by', 'workflow_note', 'vendor_name', 'invoice_number', 'invoice_date', 'belege', 'beschluss_ref', 'notes'];
    
            if ($kind === 'beschluss') {
                $legacy_field = method_exists($pods_api, 'load_field')
                    ? $pods_api->load_field(['pod' => $post_type, 'name' => 'zahlungsanweisung_ref'])
                    : null;
    
                if (!empty($legacy_field)) {
                    fs_finanzportal_verify_fail("Pods field zahlungsanweisung_ref must not remain on {$post_type}; related Zahlungsanweisungen are derived from beschluss_ref.");
                }
            }
    
            foreach ($required_fields as $field_name) {
                $field = method_exists($pods_api, 'load_field')
                    ? $pods_api->load_field(['pod' => $post_type, 'name' => $field_name])
                    : null;
    
                if (empty($field)) {
                    fs_finanzportal_verify_fail("Pods field {$field_name} is missing on {$post_type}.");
                }
    
                if (in_array($field_name, ['decided_at', 'submitted_at', 'reviewed_at', 'clarification_requested_at', 'clarification_answered_at', 'executed_at', 'invoice_date'], true)
                    && ($field['type'] ?? '') !== 'date'
                ) {
                    fs_finanzportal_verify_fail("Pods field {$field_name} on {$post_type} must be a date field.");
                }
    
                if (in_array($field_name, ['beschlussdatum', 'betrag', 'zweck_beschreibung', 'verwendungszweck'], true)
                    && (int) ($field['required'] ?? 0) !== 1
                ) {
                    fs_finanzportal_verify_fail("Pods field {$field_name} on {$post_type} must be required.");
                }
    
                if ($field_name === 'beschluss_status' || $field_name === 'zahlungs_status') {
                    $actual_values = fs_finanzportal_pick_values($field);
                    $expected_values = fs_finanzportal_expected_status_values($kind);
                    sort($actual_values);
                    sort($expected_values);
                    if ($actual_values !== $expected_values) {
                        fs_finanzportal_verify_fail("Pods field {$field_name} on {$post_type} has wrong workflow statuses: " . implode(',', $actual_values));
                    }
                }
    
                if ($field_name === 'beschluss_ref') {
                    if (($field['type'] ?? '') !== 'pick'
                        || ($field['pick_object'] ?? '') !== 'post_type'
                        || ($field['pick_val'] ?? '') !== $types['beschluss']
                        || ($field['pick_where'] ?? '') !== "beschluss_status.meta_value = 'approved'"
                        || (int) ($field['required'] ?? 0) !== 0
                    ) {
                        fs_finanzportal_verify_fail("Pods field beschluss_ref on {$post_type} must be an optional relationship to {$types['beschluss']} for Vorkasse support.");
                    }
                }
    
                if ($field_name === 'zahlungstyp') {
                    $actual_values = fs_finanzportal_pick_values($field);
                    sort($actual_values);
                    if (($field['type'] ?? '') !== 'pick'
                        || (int) ($field['required'] ?? 0) !== 1
                        || $actual_values !== ['standard', 'vorkasse']
                    ) {
                        fs_finanzportal_verify_fail("Pods field zahlungstyp on {$post_type} must be a required standard/vorkasse pick field.");
                    }
                }
    
                if ($field_name === 'vorkasse_method') {
                    $actual_values = fs_finanzportal_pick_values($field);
                    sort($actual_values);
                    if (($field['type'] ?? '') !== 'pick'
                        || $actual_values !== ['bar', 'ueberweisung']
                    ) {
                        fs_finanzportal_verify_fail("Pods field vorkasse_method on {$post_type} must be a bar/ueberweisung pick field.");
                    }
                }
    
                if (in_array($field_name, ['vorkasse_begruendung', 'empfaenger_details', 'clarification_request', 'clarification_response'], true)) {
                    if (($field['type'] ?? '') !== 'paragraph') {
                        fs_finanzportal_verify_fail("Pods field {$field_name} on {$post_type} must be a paragraph field.");
                    }
                }
            }
    
            $all_read_caps = array_merge($all_read_caps, fs_finanzportal_read_caps($capability_type));
            $all_edit_caps = array_merge($all_edit_caps, fs_finanzportal_edit_caps($capability_type));
        }
    
        $all_beschluss_read_caps = array_merge($all_beschluss_read_caps, fs_finanzportal_read_caps(fs_finanzportal_capability_type($types['beschluss'])));
        $all_beschluss_write_caps = array_merge(
            $all_beschluss_write_caps,
            array_filter(
                fs_finanzportal_edit_caps(fs_finanzportal_capability_type($types['beschluss'])),
                fn($cap) => str_starts_with($cap, 'edit_') || str_starts_with($cap, 'publish_')
            )
        );
        $all_zahlung_edit_caps = array_merge($all_zahlung_edit_caps, fs_finanzportal_edit_caps(fs_finanzportal_capability_type($types['zahlung'])));
    }
    
    foreach (['portal_admin', 'asta_finance', 'asta_reviewer', 'auditor', 'fs_portal_empty'] as $role_name) {
        if (!get_role($role_name)) {
            fs_finanzportal_verify_fail("WordPress role {$role_name} is missing.");
        }
    }
    
    $configured_meta_ledger_types = get_option('meta_ledger_post_types', []);
    if (!is_array($configured_meta_ledger_types)) {
        fs_finanzportal_verify_fail('Meta Ledger post type configuration must be an array.');
    }
    sort($configured_meta_ledger_types);
    $expected_meta_ledger_types = array_values(array_unique($all_workflow_post_types));
    sort($expected_meta_ledger_types);
    
    if ($configured_meta_ledger_types !== $expected_meta_ledger_types) {
        fs_finanzportal_verify_fail('Meta Ledger must be configured for all scoped workflow post types.');
    }
    
    if ((int) get_option('meta_ledger_retention_count') < 200) {
        fs_finanzportal_verify_fail('Meta Ledger retention must keep at least 200 entries per meta key.');
    }

    $workflow_normalization_summary = get_option('fsfp_workflow_normalization_summary');
    if (!is_array($workflow_normalization_summary)
        || !isset($workflow_normalization_summary['workflow_statuses'], $workflow_normalization_summary['zahlungstyp'], $workflow_normalization_summary['beschluss_ref'])
        || !is_array($workflow_normalization_summary['workflow_statuses'])
        || !is_array($workflow_normalization_summary['zahlungstyp'])
        || !is_array($workflow_normalization_summary['beschluss_ref'])
    ) {
        fs_finanzportal_verify_fail('Workflow normalization summary option is missing or malformed.');
    }

    foreach (['beschluss', 'zahlung'] as $kind) {
        if (!isset($workflow_normalization_summary['workflow_statuses'][$kind]['legacy_mapped'], $workflow_normalization_summary['workflow_statuses'][$kind]['reset_to_draft'])
            || !is_int($workflow_normalization_summary['workflow_statuses'][$kind]['legacy_mapped'])
            || !is_int($workflow_normalization_summary['workflow_statuses'][$kind]['reset_to_draft'])
        ) {
            fs_finanzportal_verify_fail("Workflow normalization summary must track integer counts for {$kind} status corrections.");
        }
    }

    if (!isset($workflow_normalization_summary['zahlungstyp']['reset_to_standard'])
        || !is_int($workflow_normalization_summary['zahlungstyp']['reset_to_standard'])
        || !isset($workflow_normalization_summary['beschluss_ref']['cleared_for_vorkasse'])
        || !is_int($workflow_normalization_summary['beschluss_ref']['cleared_for_vorkasse'])
    ) {
        fs_finanzportal_verify_fail('Workflow normalization summary must track integer counts for payment type resets and cleared beschluss refs.');
    }

    fs_finanzportal_verify_role_has_caps('portal_admin', ['edit_fachschaft_records', 'publish_fachschaft_records']);
    fs_finanzportal_verify_role_has_caps('asta_finance', array_values(array_unique(array_merge($all_beschluss_read_caps, $all_zahlung_edit_caps))));
    fs_finanzportal_verify_role_has_caps('asta_reviewer', array_values(array_unique(array_merge($all_beschluss_read_caps, $all_zahlung_edit_caps))));
    fs_finanzportal_verify_role_lacks_caps('asta_finance', array_values(array_unique($all_beschluss_write_caps)));
    fs_finanzportal_verify_role_lacks_caps('asta_reviewer', array_values(array_unique($all_beschluss_write_caps)));
    fs_finanzportal_verify_role_has_caps('auditor', array_values(array_unique($all_read_caps)));
    fs_finanzportal_verify_role_lacks_caps('auditor', array_filter(array_values(array_unique($all_edit_caps)), fn($cap) => str_starts_with($cap, 'edit_') || str_starts_with($cap, 'publish_')));
    fs_finanzportal_verify_role_lacks_caps('fs_portal_empty', array_filter(array_values(array_unique($all_read_caps)), fn($cap) => $cap !== 'read'));
    
    foreach ($fachschaften as $fachschaft) {
        $slug = sanitize_key($fachschaft['slug']);
        $reader_role = "fs_{$slug}_reader";
        $finance_role = "fs_{$slug}_finance";
    
        if (!get_role($reader_role) || !get_role($finance_role)) {
            fs_finanzportal_verify_fail("Fachschaft roles for {$slug} are missing.");
        }
    
        $own_read_caps = [];
        $own_edit_caps = [];
        $other_caps = [];
    
        foreach ($fachschaften as $candidate) {
            $candidate_slug = sanitize_key($candidate['slug']);
            foreach (fs_finanzportal_workflow_types($candidate_slug) as $post_type) {
                $capability_type = fs_finanzportal_capability_type($post_type);
                if ($candidate_slug === $slug) {
                    $own_read_caps = array_merge($own_read_caps, fs_finanzportal_read_caps($capability_type));
                    $own_edit_caps = array_merge($own_edit_caps, fs_finanzportal_edit_caps($capability_type));
                } else {
                    $other_caps = array_merge($other_caps, fs_finanzportal_read_caps($capability_type), fs_finanzportal_edit_caps($capability_type));
                }
            }
        }
    
        $own_write_caps = array_filter(array_values(array_unique($own_edit_caps)), fn($cap) => str_starts_with($cap, 'edit_') || str_starts_with($cap, 'publish_'));
    
        $other_workflow_caps = array_filter(array_values(array_unique($other_caps)), fn($cap) => $cap !== 'read');
    
        fs_finanzportal_verify_role_has_caps($reader_role, array_values(array_unique($own_read_caps)));
        fs_finanzportal_verify_role_lacks_caps($reader_role, array_values(array_unique(array_merge([fs_finanzportal_admin_edit_access_cap()], $own_write_caps, $other_workflow_caps))));
        fs_finanzportal_verify_role_has_caps($finance_role, array_values(array_unique(array_merge([fs_finanzportal_admin_edit_access_cap()], $own_edit_caps))));
        fs_finanzportal_verify_role_lacks_caps($finance_role, array_values(array_unique($other_workflow_caps)));
    }
    
    fs_finanzportal_verify_role_has_caps('administrator', [fs_finanzportal_admin_edit_access_cap()]);
    fs_finanzportal_verify_role_has_caps('portal_admin', [fs_finanzportal_admin_edit_access_cap()]);
    fs_finanzportal_verify_role_has_caps('asta_finance', [fs_finanzportal_admin_edit_access_cap()]);
    fs_finanzportal_verify_role_has_caps('asta_reviewer', [fs_finanzportal_admin_edit_access_cap()]);
    fs_finanzportal_verify_role_lacks_caps('auditor', [fs_finanzportal_admin_edit_access_cap()]);
    fs_finanzportal_verify_role_lacks_caps('fs_portal_empty', [fs_finanzportal_admin_edit_access_cap()]);

    return $fachschaften;
}
