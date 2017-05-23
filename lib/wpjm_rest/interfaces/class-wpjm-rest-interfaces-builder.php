<?php
/**
 * Build Stuff
 *
 * @package Mixtape
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface WPJM_REST_Interfaces_Builder
 */
interface WPJM_REST_Interfaces_Builder {
	/**
	 * Build something
	 *
	 * @return mixed
	 */
	function build();
}
