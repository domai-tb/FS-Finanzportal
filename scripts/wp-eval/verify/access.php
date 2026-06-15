<?php
/**
 * Rendered Members access verification for demo users.
 */

function fs_finanzportal_verify_access(array $restricted_pages_by_fachschaft, array $global_pages)
{
    $operations_page = fs_finanzportal_page_by_path('dashboard/betrieb');
    fs_finanzportal_verify_user_can_view(fs_finanzportal_first_user_login_with_role('administrator'), $operations_page);

    fs_finanzportal_verify_user_can_view('demo-informatik-reader', $restricted_pages_by_fachschaft['informatik'][0]);
    fs_finanzportal_verify_user_cannot_view('demo-informatik-reader', $restricted_pages_by_fachschaft['maschinenbau'][0]);
    fs_finanzportal_verify_user_cannot_view('demo-informatik-reader', $restricted_pages_by_fachschaft['philosophie'][0]);
    foreach ($global_pages as $global_page) {
        fs_finanzportal_verify_user_cannot_view('demo-informatik-reader', $global_page);
    }
    
    fs_finanzportal_verify_user_can_view('demo-maschinenbau-reader', $restricted_pages_by_fachschaft['maschinenbau'][0]);
    fs_finanzportal_verify_user_cannot_view('demo-maschinenbau-reader', $restricted_pages_by_fachschaft['informatik'][0]);
    fs_finanzportal_verify_user_cannot_view('demo-maschinenbau-reader', $restricted_pages_by_fachschaft['philosophie'][0]);
    foreach ($global_pages as $global_page) {
        fs_finanzportal_verify_user_cannot_view('demo-maschinenbau-reader', $global_page);
    }
    
    foreach (['demo-asta', 'demo-reviewer'] as $global_user) {
        foreach ($global_pages as $global_page) {
            fs_finanzportal_verify_user_can_view($global_user, $global_page);
        }
        foreach ($restricted_pages_by_fachschaft as $pages) {
            fs_finanzportal_verify_user_cannot_view($global_user, $pages[0]);
            fs_finanzportal_verify_user_cannot_view($global_user, $pages[1]);
            fs_finanzportal_verify_user_can_view($global_user, $pages[2]);
            fs_finanzportal_verify_user_cannot_view($global_user, $pages[5]);
            fs_finanzportal_verify_user_can_view($global_user, $pages[6]);
        }
    }
    
    foreach ($restricted_pages_by_fachschaft as $pages) {
        fs_finanzportal_verify_user_can_view('demo-auditor', $pages[0]);
    }
    foreach ($global_pages as $global_page) {
        fs_finanzportal_verify_user_cannot_view('demo-auditor', $global_page);
        fs_finanzportal_verify_user_cannot_view('demo-fachschaft', $global_page);
        fs_finanzportal_verify_user_cannot_view('demo-maschinenbau-finance', $global_page);
        fs_finanzportal_verify_user_cannot_view('demo-philosophie-finance', $global_page);
    }
    
    foreach ($global_pages as $global_page) {
        fs_finanzportal_verify_user_cannot_view('demo-unassigned', $global_page);
    }
    foreach ($restricted_pages_by_fachschaft as $pages) {
        fs_finanzportal_verify_user_cannot_view('demo-unassigned', $pages[0]);
    }

    foreach ([
        'demo-informatik-reader',
        'demo-maschinenbau-reader',
        'demo-asta',
        'demo-reviewer',
        'demo-auditor',
        'demo-fachschaft',
        'demo-maschinenbau-finance',
        'demo-philosophie-finance',
        'demo-unassigned',
    ] as $login) {
        fs_finanzportal_verify_user_cannot_view($login, $operations_page);
    }
    
    wp_set_current_user(0);
}
