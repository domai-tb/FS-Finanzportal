<?php
/**
 * Workflow Statuses for FS Finance Workflow.
 *
 * Registers custom post statuses that model the approval lifecycle of
 * Beschlüsse and Zahlungsanweisungen:
 *
 *   draft               – created, not yet submitted
 *   submitted           – sent to AStA reviewer for review
 *   correction_requested – reviewer asked for changes
 *   approved            – AStA reviewer / finance approved
 *   archived            – closed, no further editing allowed
 *
 * TODO: Wire statuses into PublishPress (or equivalent) workflow plugin.
 * TODO: Add allowed transitions matrix per Keycloak role.
 * TODO: Trigger email/notification hooks on status transition.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register all custom post statuses.
 */
function fsfw_register_workflow_statuses(): void {

    // ── draft ──────────────────────────────────────────────────────────────────
    // Note: WordPress already ships 'draft'; we register ours under the same
    // slug so native UI works, but keep this entry as documentation.
    // If behaviour needs to diverge, rename to 'fsfw_draft'.

    // ── submitted ──────────────────────────────────────────────────────────────
    register_post_status(
        'submitted',
        [
            'label'                     => _x( 'Eingereicht', 'post status', 'fs-finance-workflow' ),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            // translators: %s = number of posts with this status
            'label_count'               => _n_noop(
                'Eingereicht <span class="count">(%s)</span>',
                'Eingereicht <span class="count">(%s)</span>',
                'fs-finance-workflow'
            ),
        ]
    );

    // ── correction_requested ───────────────────────────────────────────────────
    register_post_status(
        'correction_requested',
        [
            'label'                     => _x( 'Korrektur erforderlich', 'post status', 'fs-finance-workflow' ),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Korrektur erforderlich <span class="count">(%s)</span>',
                'Korrektur erforderlich <span class="count">(%s)</span>',
                'fs-finance-workflow'
            ),
        ]
    );

    // ── approved ───────────────────────────────────────────────────────────────
    register_post_status(
        'approved',
        [
            'label'                     => _x( 'Genehmigt', 'post status', 'fs-finance-workflow' ),
            'public'                    => false,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Genehmigt <span class="count">(%s)</span>',
                'Genehmigt <span class="count">(%s)</span>',
                'fs-finance-workflow'
            ),
        ]
    );

    // ── archived ───────────────────────────────────────────────────────────────
    register_post_status(
        'archived',
        [
            'label'                     => _x( 'Archiviert', 'post status', 'fs-finance-workflow' ),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => false,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Archiviert <span class="count">(%s)</span>',
                'Archiviert <span class="count">(%s)</span>',
                'fs-finance-workflow'
            ),
        ]
    );
}
