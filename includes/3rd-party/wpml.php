<?php
/**
 * Only load these if WPML plugin is installed and active.
 */

/**
 * Load routines only if WPML is loaded.
 *
 * @since 1.26.0
 */
function wpml_wpjm_init() {
	add_action( 'get_job_listings_init', 'wpml_wpjm_set_language' );
	add_filter( 'wpjm_lang', 'wpml_wpjm_get_job_listings_lang' );
	add_filter( 'wpjm_page_id', 'wpml_wpjm_page_id' );
}
add_action( 'wpml_loaded', 'wpml_wpjm_init' );

/**
 * Sets WPJM's language if it is sent in the Ajax request.
 *
 * @since 1.26.0
 */
function wpml_wpjm_set_language() {
	if ( ( strstr( $_SERVER['REQUEST_URI'], '/jm-ajax/' ) || ! empty( $_GET['jm-ajax'] ) ) && isset( $_POST['lang'] ) ) {
		do_action( 'wpml_switch_language', sanitize_text_field( $_POST['lang'] ) );
	}
}

/**
 * Returns WPML's current language.
 *
 * @since 1.26.0
 *
 * @param string $lang
 * @return string
 */
function wpml_wpjm_get_job_listings_lang( $lang ) {
	return apply_filters( 'wpml_current_language', $lang );
}

/**
 * Returns the page ID for the current language.
 *
 * @param int $page_id
 * @return int
 */
function wpml_wpjm_page_id( $page_id ) {
	return apply_filters( 'wpml_object_id', $page_id, 'page', true );
}
