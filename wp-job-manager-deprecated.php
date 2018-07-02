<?php
/**
 * Deprecated functions. Do not use these.
 *
 * @package wp-job-manager
 */

if ( ! function_exists( 'order_featured_job_listing' ) ) :
	/**
	 * Was used for sorting.
	 *
	 * @deprecated 1.22.4
	 *
	 * @param array $args
	 * @return array
	 */
	function order_featured_job_listing( $args ) {
		global $wpdb;
		$args['orderby'] = "$wpdb->posts.menu_order ASC, $wpdb->posts.post_date DESC";
		return $args;
	}
endif;



if ( ! function_exists( 'the_job_type' ) ) :
	/**
	 * Displays the job type for the listing.
	 *
	 * @since 1.0.0
	 * @deprecated 1.27.0 Use `wpjm_the_job_types()` instead.
	 *
	 * @param int|WP_Post $post
	 * @return string
	 */
	function the_job_type( $post = null ) {
		_deprecated_function( __FUNCTION__, '1.27.0', 'wpjm_the_job_types' );

		if ( ! get_option( 'job_manager_enable_types' ) ) {
			return '';
		}
		$job_type = get_the_job_type( $post );
		if ( $job_type ) {
			echo esc_html( $job_type->name );
		}
	}
endif;

if ( ! function_exists( 'get_the_job_type' ) ) :
	/**
	 * Gets the job type for the listing.
	 *
	 * @since 1.0.0
	 * @deprecated 1.27.0 Use `wpjm_get_the_job_types()` instead.
	 *
	 * @param int|WP_Post $post (default: null).
	 * @return string|bool|null
	 */
	function get_the_job_type( $post = null ) {
		_deprecated_function( __FUNCTION__, '1.27.0', 'wpjm_get_the_job_types' );

		$post = get_post( $post );
		if ( 'job_listing' !== $post->post_type ) {
			return;
		}

		$types = wp_get_post_terms( $post->ID, 'job_listing_type' );

		if ( $types ) {
			$type = current( $types );
		} else {
			$type = false;
		}

		return apply_filters( 'the_job_type', $type, $post );
	}
endif;

if ( ! function_exists( 'wpjm_get_permalink_structure' ) ) :
	/**
	 * Retrieves permalink settings. Moved to `WP_Job_Manager_Post_Types` class in 1.28.0.
	 *
	 * @since 1.27.0
	 * @deprecated 1.28.0
	 * @return array
	 */
	function wpjm_get_permalink_structure() {
		return WP_Job_Manager_Post_Types::get_permalink_structure();
	}
endif;
