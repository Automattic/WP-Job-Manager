<?php
/**
 * File containing the psalm loader.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
	define( 'ABSPATH', __DIR__ . '/../' );
}

if ( ! defined( 'JOB_MANAGER_PLUGIN_DIR' ) ) {
	define( 'JOB_MANAGER_PLUGIN_DIR', ABSPATH );
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/php-stubs/wordpress-stubs/wordpress-stubs.php';
