<?php
/**
 * Adds additional compatibility with Jetpack.
 */

/**
 * Skip filled job listings.
 *
 * @param bool    $skip_post
 * @param WP_Post $post
 * @return bool
 */
function wpjm_jetpack_skip_filled_job_listings( $skip_post, $post ) {
	if ( 'job_listing' !== $post->post_type ) {
		return $skip_post;
	}

	if ( is_position_filled( $post ) ) {
		return true;
	}

	return $skip_post;
}
add_action( 'jetpack_sitemap_skip_post', 'wpjm_jetpack_skip_filled_job_listings', 10, 2 );

/**
 * Add `job_listing` post type to sitemap.
 *
 * @param array $post_types
 * @return array
 */
function wpjm_jetpack_add_post_type( $post_types ) {
	$post_types[] = 'job_listing';
	return $post_types;
}
add_filter( 'jetpack_sitemap_post_types', 'wpjm_jetpack_add_post_type' );
