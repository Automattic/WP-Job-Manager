<?php
/**
 * Only load these if WPML plugin is installed and active.
 *
 * @package wp-job-manager
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
	add_filter( 'job_manager_permalink_structure', 'wpml_wpjm_force_static_slugs' );

	$default_lang = apply_filters( 'wpml_default_language', null );
	$current_lang = apply_filters( 'wpml_current_language', null );

	// Add filter only for non default languages.
	if ( $current_lang !== $default_lang ) {
		add_filter( 'job_manager_settings', 'wpml_wpjm_hide_page_selection' );
	}
}

add_action( 'wpml_loaded', 'wpml_wpjm_init' );
add_action( 'wpml_loaded', 'wpml_wpjm_set_language' ); // Intentional: see docblock above (WPML 3.7.1 ordering).

/**
 * Sets WPJM's language if it is sent in the Ajax request.
 * Note: This is hooked into both `wpml_loaded` and `get_job_listings_init`. As of WPML 3.7.1, if it was hooked
 * into just `wpml_loaded` the query doesn't get the correct language for job listings. If it is just hooked into
 * `get_job_listings_init` the locale doesn't get set correctly and the string translations are only loaded from
 * the default language.
 *
 * @since 1.26.0
 */
function wpml_wpjm_set_language() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Input is used safely.
	$input_lang = isset( $_POST['lang'] ) ? sanitize_text_field( wp_unslash( $_POST['lang'] ) ) : false;

	if (
		isset( $_SERVER['REQUEST_URI'] )
		&& (
			strstr( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), '/jm-ajax/' )
			|| ! empty( $_GET['jm-ajax'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Input is used safely.
		)
		&& $input_lang
	) {
		do_action( 'wpml_switch_language', $input_lang );
	}
}

/**
 * Returns WPML's current language.
 *
 * @since 1.26.0
 *
 * @param string $lang
 *
 * @return string
 */
function wpml_wpjm_get_job_listings_lang( $lang ) {
	return apply_filters( 'wpml_current_language', $lang );
}

/**
 * Returns the page ID for the current language.
 *
 * @param int $page_id
 *
 * @return int
 */
function wpml_wpjm_page_id( $page_id ) {
	return apply_filters( 'wpml_object_id', $page_id, 'page', true );
}

/**
 * Forces stable rewrite slugs so WPML can register the post type and taxonomies as translatable.
 *
 * WPML captures the source rewrite slug at `register_post_type()` / `register_taxonomy()` time. When
 * the slug is dynamic (per-site permalink option), WPML cannot find a stable source to translate
 * against, which breaks translated URLs and the AJAX request used by the [jobs] shortcode.
 *
 * See https://wpml.org/errata/wp-job-manager-job-incorrect-slug-translation-for-job-listings/ (compsupp-7570).
 *
 * @since $$next-version$$
 *
 * @param array $permalinks The resolved permalink structure.
 *
 * @return array
 */
function wpml_wpjm_force_static_slugs( $permalinks ) {
	if ( empty( $permalinks['job_base'] ) ) {
		$permalinks['job_rewrite_slug'] = 'job';
	}
	if ( empty( $permalinks['category_base'] ) ) {
		$permalinks['category_rewrite_slug'] = 'job-category';
	}
	if ( empty( $permalinks['type_base'] ) ) {
		$permalinks['type_rewrite_slug'] = 'job-type';
	}
	if ( empty( $permalinks['jobs_archive'] ) ) {
		$permalinks['jobs_archive_rewrite_slug'] = 'job-listings';
	}

	return $permalinks;
}

/**
 * Set WPJM page options to hidden for non default languages.
 *
 * @since 1.31.0
 *
 * @param array $settings
 *
 * @return array
 */
function wpml_wpjm_hide_page_selection( $settings ) {
	foreach ( $settings['job_pages'][1] as $key => $setting ) {
		if ( 'page' !== $setting['type'] ) {
			continue;
		}
		$setting['type']        = 'hidden';
		$setting['human_value'] = __( 'Page Not Set', 'wp-job-manager' );
		$current_value          = get_option( $setting['name'] );
		if ( $current_value ) {
			$page = get_post( apply_filters( 'wpml_object_id', $current_value, 'page' ) );

			if ( $page ) {
				$setting['human_value'] = $page->post_title;
			}
		}

		$default_lang     = apply_filters( 'wpml_default_language', null );
		$url_to_edit_page = admin_url( 'edit.php?post_type=job_listing&page=job-manager-settings&lang=' . $default_lang . '#settings-job_pages' );

		// translators: Placeholder (%s) is the URL to edit the primary language in WPML.
		$setting['desc']                  = sprintf( __( '<a href="%s">Switch to primary language</a> to edit this setting.', 'wp-job-manager' ), esc_url( $url_to_edit_page ) );
		$settings['job_pages'][1][ $key ] = $setting;
	}

	return $settings;
}
