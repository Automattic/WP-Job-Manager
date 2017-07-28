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
	add_filter( 'wpjm_lang', 'wpml_wpjm_get_job_listings_lang' );
	add_filter( 'wpjm_ajax_endpoint', 'wpml_wpjm_add_lang_to_ajax_endpoint' );
	add_filter( 'wpjm_page_id', 'wpml_wpjm_page_id' );
}
add_action( 'wpml_loaded', 'wpml_wpjm_init' );

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
 * @since 1.26.0
 *
 * @param int $page_id
 * @return int
 */
function wpml_wpjm_page_id( $page_id ) {
	return apply_filters( 'wpml_object_id', $page_id, 'page', true );
}

/**
 * Add language to ajax endpoint.
 *
 * @since 1.28.0
 *
 * @param string $endpoint
 * @return string
 */
function wpml_wpjm_add_lang_to_ajax_endpoint( $endpoint ) {
	$lang = apply_filters( 'wpml_current_language', null );
	if ( ! empty( $lang ) ) {
		$endpoint = add_query_arg( 'lang', $lang, $endpoint );
	}
	return $endpoint;
}
