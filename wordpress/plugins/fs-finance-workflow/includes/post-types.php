<?php
/**
 * Custom Post Types for FS Finance Workflow.
 *
 * Registers three CPTs:
 *  - fachschaft        – represents a Fachschaft (student council unit)
 *  - beschluss         – a formal resolution passed by a Fachschaft
 *  - zahlungsanweisung – a payment order derived from a Beschluss
 *
 * TODO: Add capability_type per role once Keycloak role mapping is wired up.
 * TODO: Add meta boxes / ACF field groups for each CPT.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register all custom post types.
 */
function fsfw_register_post_types(): void {

    // ── Fachschaft ─────────────────────────────────────────────────────────────
    register_post_type(
        'fachschaft',
        [
            'label'               => __( 'Fachschaften', 'fs-finance-workflow' ),
            'labels'              => [
                'name'          => __( 'Fachschaften', 'fs-finance-workflow' ),
                'singular_name' => __( 'Fachschaft', 'fs-finance-workflow' ),
                'add_new_item'  => __( 'Fachschaft hinzufügen', 'fs-finance-workflow' ),
                'edit_item'     => __( 'Fachschaft bearbeiten', 'fs-finance-workflow' ),
            ],
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_rest'        => true,
            'supports'            => [ 'title', 'editor', 'custom-fields' ],
            'menu_icon'           => 'dashicons-groups',
            'rewrite'             => false,
            'has_archive'         => false,
        ]
    );

    // ── Beschluss ──────────────────────────────────────────────────────────────
    register_post_type(
        'beschluss',
        [
            'label'               => __( 'Beschlüsse', 'fs-finance-workflow' ),
            'labels'              => [
                'name'          => __( 'Beschlüsse', 'fs-finance-workflow' ),
                'singular_name' => __( 'Beschluss', 'fs-finance-workflow' ),
                'add_new_item'  => __( 'Beschluss erstellen', 'fs-finance-workflow' ),
                'edit_item'     => __( 'Beschluss bearbeiten', 'fs-finance-workflow' ),
            ],
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_rest'        => true,
            'supports'            => [ 'title', 'editor', 'custom-fields', 'revisions' ],
            'menu_icon'           => 'dashicons-text-page',
            'rewrite'             => false,
            'has_archive'         => false,
        ]
    );

    // ── Zahlungsanweisung ──────────────────────────────────────────────────────
    register_post_type(
        'zahlungsanweisung',
        [
            'label'               => __( 'Zahlungsanweisungen', 'fs-finance-workflow' ),
            'labels'              => [
                'name'          => __( 'Zahlungsanweisungen', 'fs-finance-workflow' ),
                'singular_name' => __( 'Zahlungsanweisung', 'fs-finance-workflow' ),
                'add_new_item'  => __( 'Zahlungsanweisung erstellen', 'fs-finance-workflow' ),
                'edit_item'     => __( 'Zahlungsanweisung bearbeiten', 'fs-finance-workflow' ),
            ],
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_rest'        => true,
            'supports'            => [ 'title', 'editor', 'custom-fields', 'revisions' ],
            'menu_icon'           => 'dashicons-money-alt',
            'rewrite'             => false,
            'has_archive'         => false,
        ]
    );
}
