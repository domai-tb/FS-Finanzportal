<?php
/**
 * Setup-time helpers for FS-Finanzportal.
 */

function fsfp_cli_normalize_workflow_statuses(array $fachschaften): void
{
    $summary = [
        'workflow_statuses' => [
            'beschluss' => [
                'legacy_mapped' => 0,
                'reset_to_draft' => 0,
            ],
            'zahlung' => [
                'legacy_mapped' => 0,
                'reset_to_draft' => 0,
            ],
        ],
        'zahlungstyp' => [
            'reset_to_standard' => 0,
        ],
        'beschluss_ref' => [
            'cleared_for_vorkasse' => 0,
        ],
    ];

    foreach ($fachschaften as $fachschaft) {
        $slug = sanitize_key($fachschaft['slug']);
        $types = fsfp_cli_workflow_types($slug);

        foreach ([
            ['post_type' => $types['beschluss'], 'field' => 'beschluss_status', 'valid' => fsfp_cli_workflow_status_values('beschluss'), 'map' => []],
            ['post_type' => $types['zahlung'], 'field' => 'zahlungs_status', 'valid' => fsfp_cli_workflow_status_values('zahlung'), 'map' => fsfp_cli_legacy_zahlung_status_map()],
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
                    $new_status = $workflow['map'][$status];
                    if ($status !== $new_status) {
                        update_post_meta((int) $post_id, $workflow['field'], $new_status);
                        $summary['workflow_statuses'][$workflow['field'] === 'beschluss_status' ? 'beschluss' : 'zahlung']['legacy_mapped']++;
                    }
                } elseif (!in_array($status, $workflow['valid'], true)) {
                    if ($status !== 'draft') {
                        update_post_meta((int) $post_id, $workflow['field'], 'draft');
                        $summary['workflow_statuses'][$workflow['field'] === 'beschluss_status' ? 'beschluss' : 'zahlung']['reset_to_draft']++;
                    }
                }
            }
        }

        $zahlung_ids = get_posts([
            'post_type' => $types['zahlung'],
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => -1,
        ]);

        foreach ($zahlung_ids as $post_id) {
            $zahlungstyp = (string) get_post_meta((int) $post_id, 'zahlungstyp', true);
            if (!in_array($zahlungstyp, fsfp_cli_payment_type_values(), true)) {
                if ($zahlungstyp !== 'standard') {
                    update_post_meta((int) $post_id, 'zahlungstyp', 'standard');
                    $summary['zahlungstyp']['reset_to_standard']++;
                }
                continue;
            }

            if ($zahlungstyp === 'vorkasse') {
                if (metadata_exists('post', (int) $post_id, 'beschluss_ref')) {
                    delete_post_meta((int) $post_id, 'beschluss_ref');
                    $summary['beschluss_ref']['cleared_for_vorkasse']++;
                }
            }
        }
    }

    update_option('fsfp_workflow_normalization_summary', $summary);
}

function fsfp_cli_workflow_status_values(string $kind): array
{
    $pods_config = fsfp_cli_load_json_config('portal/pods.json', 'Portal Pods config JSON is invalid.');
    $fields = $pods_config['workflows'][$kind]['fields'] ?? [];

    foreach ($fields as $field) {
        if (($field['type'] ?? '') === 'status') {
            return array_keys($field['options'] ?? []);
        }
    }

    WP_CLI::error("Missing status field config for {$kind}.");
}

function fsfp_cli_payment_type_values(): array
{
    $pods_config = fsfp_cli_load_json_config('portal/pods.json', 'Portal Pods config JSON is invalid.');
    $fields = $pods_config['workflows']['zahlung']['fields'] ?? [];

    foreach ($fields as $field) {
        if (($field['name'] ?? '') === 'zahlungstyp') {
            return array_keys($field['options'] ?? []);
        }
    }

    WP_CLI::error('Missing payment type config.');
}

function fsfp_cli_legacy_zahlung_status_map(): array
{
    return [
        'approved' => 'executed',
        'rejected' => 'correction_requested',
        'archived' => 'cancelled',
    ];
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
