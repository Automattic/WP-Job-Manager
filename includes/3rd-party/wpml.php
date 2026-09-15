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
	add_action( 'job_manager_my_job_do_action', 'wpml_wpjm_delete_translations', 10, 2 );
	add_filter( 'wpjm_lang', 'wpml_wpjm_get_job_listings_lang' );
	add_filter( 'wpjm_page_id', 'wpml_wpjm_page_id' );

	$default_lang = apply_filters( 'wpml_default_language', null );
	$current_lang = apply_filters( 'wpml_current_language', null );

	// Add filter only for non default languages.
	if ( $current_lang !== $default_lang ) {
		add_filter( 'job_manager_settings', 'wpml_wpjm_hide_page_selection' );
	}
}

add_action( 'wpml_loaded', 'wpml_wpjm_init' );
add_action( 'wpml_loaded', 'wpml_wpjm_set_language' );

/**
 * Trashes the translations of a job listing that was deleted from the job dashboard.
 *
 * WPML only hooks its delete sync on REST requests, so a listing deleted from the
 * frontend dashboard leaves its translations published. The dashboard action is used
 * to trash them here. Translations are always trashed, whether or not WPML's own
 * "delete translations" setting is on, because the dashboard should not leave a
 * published copy of a listing the author removed.
 *
 * @since 2.4.8
 *
 * @param string $action The dashboard action being handled.
 * @param int    $job_id The ID of the job listing the action was performed on. Default 0.
 */
function wpml_wpjm_delete_translations( $action, $job_id = 0 ) {
	if ( 'delete' !== $action ) {
		return;
	}

	$job_id       = absint( $job_id );
	$element_type = apply_filters( 'wpml_element_type', \WP_Job_Manager_Post_Types::PT_LISTING );
	$trid         = apply_filters( 'wpml_element_trid', null, $job_id, $element_type );

	if ( ! $trid ) {
		return;
	}

	/*
	 * The `all_statuses` argument is set because WPML hides non-public translations
	 * from a user who cannot edit private posts. An employer on the frontend would
	 * otherwise be left with translations in draft or pending.
	 */
	$translations = apply_filters( 'wpml_get_element_translations', null, $trid, $element_type, false, true );

	if ( empty( $translations ) ) {
		return;
	}

	foreach ( $translations as $translation ) {
		$translation_id = isset( $translation->element_id ) ? absint( $translation->element_id ) : 0;

		// The listing itself is already trashed by the dashboard handler.
		if ( ! $translation_id || $translation_id === $job_id ) {
			continue;
		}

		wp_trash_post( $translation_id );
	}
}

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
