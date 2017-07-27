<?php
/**
 * Adds additional compatibility with Yoast SEO.
 */

// Yoast SEO will by default include the `job_listing` post type because it is flagged as public.

/**
 * Skip filled job listings.
 *
 * @param array  $url  Array of URL parts.
 * @param string $type URL type.
 * @param object $user Data object for the URL.
 * @return string|bool False if we're skipping
 */
function wpjm_yoast_skip_filled_job_listings( $url, $type, $post ) {
	if ( 'job_listing' !== $post->post_type ) {
		return $url;
	}

	if ( is_position_filled( $post ) ) {
		return false;
	}

	return $url;
}
add_action( 'wpseo_sitemap_entry', 'wpjm_yoast_skip_filled_job_listings', 10, 3 );
