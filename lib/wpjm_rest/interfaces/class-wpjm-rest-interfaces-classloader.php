<?php
/**
 * A Class Loader Interface.
 *
 * Injected into the Bootstrap. Handles all class loading.
 *
 * @package Mixtape
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface WPJM_REST_Interfaces_Classloader
 */
interface WPJM_REST_Interfaces_Classloader {
	/**
	 * Load a class
	 *
	 * @param string $name The class to load.
	 * @return WPJM_REST_Interfaces_Classloader
	 */
	function load_class( $name );
}
