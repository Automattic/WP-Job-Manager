<?php
/**
 * Adds additional compatibility with All in One SEO Pack.
 *
 * @package wp-job-manager
 */

/**
 * Skip filled job listings, and listings the anonymous crawler may not see.
 *
 * @param WP_Post[] $posts
 * @return WP_Post[]
 */
function wpjm_aiosp_sitemap_filter_filled_jobs( $posts ) {
	$can_browse = job_manager_user_can_browse_job_listings();

	foreach ( $posts as $index => $post ) {
		if ( $post instanceof WP_Post && \WP_Job_Manager_Post_Types::PT_LISTING !== $post->post_type ) {
			continue;
		}
		if ( is_position_filled( $post ) ) {
			unset( $posts[ $index ] );
			continue;
		}
		// Keep restricted listings out of the sitemap, matching the Yoast and Jetpack
		// integrations: it is crawled anonymously, and an anonymous requester cannot
		// satisfy a configured view capability; a browse-capability restriction makes
		// listing indexes non-public, and a sitemap is such an index.
		if ( $post instanceof WP_Post
			&& ( ! $can_browse || ! job_manager_user_can_view_job_listing( $post->ID ) ) ) {
			unset( $posts[ $index ] );
		}
	}
	return $posts;
}
add_action( 'aiosp_sitemap_post_filter', 'wpjm_aiosp_sitemap_filter_filled_jobs', 10, 3 );

/**
 * Keeps restricted job listings out of All in One SEO v4 sitemaps.
 *
 * AIOSEO v4 builds its sitemaps from its own queries and exposes exclusion by post ID
 * (`aioseo_sitemap_exclude_posts`) rather than by post type, so when a browse or view
 * capability restriction is configured — which an anonymous crawler can never satisfy —
 * every listing ID is added to the exclusion list. The lookup only runs on restricted
 * boards; the default (unrestricted) configuration returns immediately.
 *
 * @param array  $ids  Post IDs excluded from the sitemap.
 * @param string $type Sitemap type.
 * @return array
 */
function wpjm_aioseo_sitemap_exclude_restricted_listings( $ids, $type ) {
	if ( job_manager_user_can_browse_job_listings() && ! \WP_Job_Manager_Post_Types::viewer_denied_by_view_cap() ) {
		return $ids;
	}

	$listing_ids = get_posts(
		[
			'post_type'        => \WP_Job_Manager_Post_Types::PT_LISTING,
			'post_status'      => 'publish',
			'fields'           => 'ids',
			'numberposts'      => -1,
			'suppress_filters' => false,
		]
	);

	return array_merge( (array) $ids, array_map( 'intval', $listing_ids ) );
}
add_filter( 'aioseo_sitemap_exclude_posts', 'wpjm_aioseo_sitemap_exclude_restricted_listings', 10, 2 );
