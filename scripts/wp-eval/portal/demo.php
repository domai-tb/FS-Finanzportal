<?php
/**
 * Demo content seeding for the setup-time portal import.
 */

function fsfp_cli_seed_portal_demo(array $fachschaften): void
{
    foreach ($fachschaften as $fachschaft) {
        fsfp_cli_upsert_post('fachschaft', sanitize_key($fachschaft['slug']), $fachschaft['label']);
    }
    
    $users = [
        ['demo-fachschaft', 'demo-fachschaft@example.com', 'fs_informatik_finance', 'informatik'],
        ['demo-informatik-reader', 'demo-informatik-reader@example.com', 'fs_informatik_reader', 'informatik'],
        ['demo-informatik-reader2', 'demo-informatik-reader2@example.com', 'fs_informatik_reader', 'informatik'],
        ['demo-maschinenbau-finance', 'demo-maschinenbau-finance@example.com', 'fs_maschinenbau_finance', 'maschinenbau'],
        ['demo-maschinenbau-reader', 'demo-maschinenbau-reader@example.com', 'fs_maschinenbau_reader', 'maschinenbau'],
        ['demo-maschinenbau-reader2', 'demo-maschinenbau-reader2@example.com', 'fs_maschinenbau_reader', 'maschinenbau'],
        ['demo-philosophie-finance', 'demo-philosophie-finance@example.com', 'fs_philosophie_finance', 'philosophie'],
        ['demo-philosophie', 'demo-philosophie@example.com', 'fs_philosophie_reader', 'philosophie'],
        ['demo-philosophie-reader2', 'demo-philosophie-reader2@example.com', 'fs_philosophie_reader', 'philosophie'],
        ['demo-asta', 'demo-asta@example.com', 'asta_finance', ''],
        ['demo-reviewer', 'demo-reviewer@example.com', 'asta_reviewer', ''],
        ['demo-auditor', 'demo-auditor@example.com', 'auditor', ''],
        ['demo-unassigned', 'demo-unassigned@example.com', 'fs_portal_empty', ''],
    ];
    
    foreach ($users as $user) {
        fsfp_cli_upsert_user($user[0], $user[1], $user[2], $user[3]);
    }
    
    $demo_file = fsfp_cli_config_path('demo/beschluesse.json');
    $items = json_decode(file_get_contents($demo_file), true);
    if (!is_array($items)) {
        WP_CLI::error('Invalid demo Beschluesse JSON.');
    }
    
    foreach ($items as $item) {
        $fachschaft = sanitize_key($item['fachschaft'] ?? '');
        if ($fachschaft === '') {
            WP_CLI::error('Demo Beschluss is missing Fachschaft.');
        }
    
        $post_type = fsfp_cli_workflow_types($fachschaft)['beschluss'];
        $author = get_user_by('login', $item['author'] ?? 'demo-fachschaft');
        $post_id = fsfp_cli_upsert_post($post_type, $item['slug'], $item['title'], [
            'post_author' => $author ? $author->ID : 1,
            'post_status' => 'publish',
        ]);
    
        foreach (['fachschaft', 'beschlussdatum', 'betrag', 'zweck_beschreibung', 'notes'] as $field) {
            update_post_meta($post_id, $field, $item[$field] ?? '');
        }
    
        update_post_meta($post_id, 'beschluss_status', $item['status'] ?? 'draft');
    }
    
    $vorkasse_file = fsfp_cli_config_path('demo/vorkasse.json');
    if (is_readable($vorkasse_file)) {
        $vorkasse_items = json_decode(file_get_contents($vorkasse_file), true);
        if (!is_array($vorkasse_items)) {
            WP_CLI::error('Invalid demo Vorkasse JSON.');
        }
    
        foreach ($vorkasse_items as $item) {
            $fachschaft = sanitize_key($item['fachschaft'] ?? '');
            if ($fachschaft === '') {
                WP_CLI::error('Demo Vorkasse payment is missing Fachschaft.');
            }
    
            $post_type = fsfp_cli_workflow_types($fachschaft)['zahlung'];
            $author = get_user_by('login', $item['author'] ?? 'demo-fachschaft');
            $post_id = fsfp_cli_upsert_post($post_type, $item['slug'], $item['title'], [
                'post_author' => $author ? $author->ID : 1,
                'post_status' => 'publish',
            ]);
    
            foreach ([
                'fachschaft',
                'betrag',
                'verwendungszweck',
                'vorkasse_method',
                'vorkasse_begruendung',
                'empfaenger_details',
                'workflow_note',
                'notes',
            ] as $field) {
                update_post_meta($post_id, $field, $item[$field] ?? '');
            }
    
            update_post_meta($post_id, 'zahlungstyp', 'vorkasse');
            update_post_meta($post_id, 'zahlungs_status', $item['status'] ?? 'draft');
            delete_post_meta($post_id, 'beschluss_ref');
        }
    }
}
