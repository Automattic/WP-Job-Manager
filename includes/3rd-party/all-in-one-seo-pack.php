<?php
/**
 * Adds additional compatibility with All in One SEO Pack.
 *
 * @package wp-job-manager
 */

/**
 * Skip filled job listings.
 *
 * @param WP_Post[] $posts
 * @return WP_Post[]
 */
function wpjm_aiosp_sitemap_filter_filled_jobs( $posts ) {
	foreach ( $posts as $index => $post ) {
		if ( $post instanceof WP_Post && 'job_listing' !== $post->post_type ) {
			continue;
		}
		if ( is_position_filled( $post ) ) {
			unset( $posts[ $index ] );
		}
	}
	return $posts;
}
add_action( 'aiosp_sitemap_post_filter', 'wpjm_aiosp_sitemap_filter_filled_jobs', 10, 3 );
