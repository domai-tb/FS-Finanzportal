<?php
/**
 * Plugin Name:  FS Finance Workflow
 * Plugin URI:   https://github.com/domai-tb/FS-Finanzportal
 * Description:  Minimal workflow plugin for Fachschaft finance processes.
 *               Registers custom post types (Fachschaft, Beschluss,
 *               Zahlungsanweisung) and basic workflow statuses.
 *               Full accounting is intentionally out of scope.
 * Version:      0.1.0
 * Author:       FS-Finanzportal Contributors
 * License:      GPL-2.0-or-later
 * Text Domain:  fs-finance-workflow
 * Domain Path:  /languages
 *
 * TODO: Add Keycloak role → WordPress capability mapping once the
 *       daggerhart-openid-connect-generic plugin is configured.
 * TODO: Add export hooks (CSV / PDF) for AStA accounting handover.
 * TODO: Add server-side validation for required Beschluss fields.
 */

defined( 'ABSPATH' ) || exit;

// ── Autoload includes ──────────────────────────────────────────────────────────
require_once plugin_dir_path( __FILE__ ) . 'includes/post-types.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/workflow-statuses.php';

// ── Bootstrap ──────────────────────────────────────────────────────────────────
add_action( 'init', 'fsfw_register_post_types' );
add_action( 'init', 'fsfw_register_workflow_statuses' );
