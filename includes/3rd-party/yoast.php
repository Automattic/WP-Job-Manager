<?php
/**
 * Adds additional compatibility with Yoast SEO.
 *
 * Yoast SEO will by default include the `job_listing` post type because it is flagged as public.
 *
 * @package wp-job-manager
 */

/**
 * Skip filled job listings.
 *
 * @param array  $url  Array of URL parts.
 * @param string $type URL type.
 * @param object $post Post object.
 * @return string|bool False if we're skipping.
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

/**
 * Links schema to Yoast SEO schema if Yoast SEO is loaded.
 *
 * @param array $data The schema data.
 *
 * @return array The schema data.
 */
function wpjm_link_schema_to_yoast_schema( $data ) {
	if ( function_exists( 'YoastSEO' ) ) {
		$data['mainEntityOfPage']    = [ '@id' => YoastSEO()->meta->for_current_page()->canonical ];
		$data['identifier']['value'] = YoastSEO()->meta->for_current_page()->canonical;
	}
	return $data;
}
add_filter( 'wpjm_get_job_listing_structured_data', 'wpjm_link_schema_to_yoast_schema' );
