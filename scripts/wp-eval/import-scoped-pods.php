<?php
/**
 * Builds and imports the Fachschaft-scoped Pods model from configuration.
 *
 * This setup-time entrypoint keeps WordPress free of project-specific runtime
 * PHP while making the repeated Pods configuration reproducible.
 */

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/pods.php';
require_once __DIR__ . '/pods/fields.php';
require_once __DIR__ . '/pods/schema.php';
require_once __DIR__ . '/pods/import.php';
