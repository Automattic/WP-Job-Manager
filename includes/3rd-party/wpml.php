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
	add_action( 'update_option_' . WP_Job_Manager_Post_Types::PERMALINK_OPTION_NAME, 'wpml_wpjm_reset_static_slugs' );

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
 * The slugs are saved once, from a front end request in the default language, so existing URLs in the
 * default language keep working. Rewrite rules are flushed once after the slugs are saved. Saving the
 * permalink settings clears them, so they are picked again.
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
	$base_to_slug = [
		'job_base'      => 'job_rewrite_slug',
		'category_base' => 'category_rewrite_slug',
		'type_base'     => 'type_rewrite_slug',
		'jobs_archive'  => 'jobs_archive_rewrite_slug',
	];

	$default_lang = apply_filters( 'wpml_default_language', null );
	$stable_slugs = get_option( 'job_manager_wpml_rewrite_slugs' );

	// Pick the slugs again when none are saved yet or the default language changed.
	if ( ! is_array( $stable_slugs ) || ( $stable_slugs['language'] ?? null ) !== $default_lang ) {
		$current_lang = apply_filters( 'wpml_current_language', null );

		// Translated slugs only differ outside the default language, so wait for a request in it.
		if (
			is_admin()
			|| wp_doing_ajax()
			|| empty( $current_lang )
			|| $default_lang !== $current_lang
		) {
			return $permalinks;
		}

		$stable_slugs             = wp_array_slice_assoc( $permalinks, array_values( $base_to_slug ) );
		$stable_slugs['language'] = $current_lang;
		update_option( 'job_manager_wpml_rewrite_slugs', $stable_slugs );
		add_action( 'wp_loaded', 'wpml_wpjm_flush_rewrite_rules' );
	}

	foreach ( $base_to_slug as $base => $slug ) {
		if ( empty( $permalinks[ $base ] ) && ! empty( $stable_slugs[ $slug ] ) ) {
			$permalinks[ $slug ] = $stable_slugs[ $slug ];
		}
	}

	return $permalinks;
}

/**
 * Clears the saved stable slugs when the permalink settings change, so they are picked again.
 *
 * @since $$next-version$$
 */
function wpml_wpjm_reset_static_slugs() {
	delete_option( 'job_manager_wpml_rewrite_slugs' );
}

/**
 * Flushes rewrite rules once after the stable slugs are saved, so WPML can add the translated slug rules.
 *
 * @since $$next-version$$
 */
function wpml_wpjm_flush_rewrite_rules() {
	flush_rewrite_rules( false );
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
