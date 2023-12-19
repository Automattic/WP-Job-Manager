<?php
/**
 * Only load these if Polylang plugin is installed and active.
 *
 * @package wp-job-manager
 */

/**
 * Load routines only if Polylang is loaded.
 *
 * @since 1.26.0
 */
function polylang_wpjm_init() {
	add_filter( 'wpjm_lang', 'polylang_wpjm_get_job_listings_lang' );
	add_filter( 'wpjm_page_id', 'polylang_wpjm_page_id' );
	add_action( 'get_job_listings_query_args', 'polylang_wpjm_query_language' );
}
add_action( 'pll_init', 'polylang_wpjm_init' );


/**
 * Sets the current language when running job listings query.
 *
 * @since 1.29.1
 *
 * @param array $query_args
 * @return array
 */
function polylang_wpjm_query_language( $query_args ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Input is used safely.
	$input_lang = isset( $_POST['lang'] ) ? sanitize_text_field( wp_unslash( $_POST['lang'] ) ) : false;

	if ( $input_lang ) {
		$query_args['lang'] = $input_lang;
	}
	return $query_args;
}

/**
 * Returns Polylang's current language.
 *
 * @since 1.26.0
 *
 * @param string $lang
 * @return string
 */
function polylang_wpjm_get_job_listings_lang( $lang ) {
	if (
		function_exists( 'pll_current_language' )
		&& function_exists( 'pll_is_translated_post_type' )
		&& pll_is_translated_post_type( \WP_Job_Manager_Post_Types::PT_LISTING )
	) {
		return pll_current_language();
	}
	return $lang;
}

/**
 * Returns the page ID for the current language.
 *
 * @since 1.26.0
 *
 * @param int $page_id
 * @return int
 */
function polylang_wpjm_page_id( $page_id ) {
	if ( function_exists( 'pll_get_post' ) ) {
		$page_id = pll_get_post( $page_id );
	}
	return absint( $page_id );
}

/**
 * Tells Polylang about ajax requests
 * The filter is applied *before* the action 'pll_init'
 *
 * @since 1.32.0
 *
 * @param bool $is_ajax
 * @return bool
 */
function polylang_wpjm_doing_ajax( $is_ajax ) {
	return isset( $_SERVER['REQUEST_URI'] ) && false === strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), '/jm-ajax/' ) ? $is_ajax : true;
}
add_filter( 'pll_is_ajax_on_front', 'polylang_wpjm_doing_ajax' );
