<?php
/**
 * File containing the class WP_Job_Manager_REST_API.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles functionality related to the REST API.
 *
 * @since 1.33.0
 */
class WP_Job_Manager_REST_API {
	/**
	 * Sets up initial hooks.
	 *
	 * @static
	 */
	public static function init() {
		add_filter( 'rest_prepare_job_listing', [ __CLASS__, 'prepare_job_listing' ], 10, 2 );
		add_filter( 'rest_job_listing_query', [ __CLASS__, 'exclude_filled_from_query' ], 10, 2 );
	}

	/**
	 * Excludes filled job listings from REST API query results.
	 *
	 * @param array           $args    Array of query arguments.
	 * @param WP_REST_Request $request The REST API request.
	 * @return array
	 */
	public static function exclude_filled_from_query( $args, $request ) {
		if ( 1 !== absint( get_option( 'job_manager_hide_filled_positions' ) ) ) {
			return $args;
		}

		if ( ! isset( $args['meta_query'] ) ) {
			$args['meta_query'] = []; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Empty.
		}

		$args['meta_query'][] = [
			'relation' => 'OR',
			[
				'key'     => '_filled',
				'value'   => '1',
				'compare' => '!=',
			],
			[
				'key'     => '_filled',
				'compare' => 'NOT EXISTS',
			],
		];

		return $args;
	}

	/**
	 * Filters the job listing data for a REST API response.
	 *
	 * @param WP_REST_Response $response The response object.
	 * @param WP_Post          $post     Post object.
	 * @return WP_REST_Response
	 */
	public static function prepare_job_listing( $response, $post ) {
		$current_user = wp_get_current_user();
		$fields       = WP_Job_Manager_Post_Types::get_job_listing_fields();
		$data         = $response->get_data();

		if ( ! isset( $data['meta'] ) ) {
			$data['meta'] = [];
		}

		foreach ( $data['meta'] as $meta_key => $meta_value ) {
			if ( isset( $fields[ $meta_key ] ) && is_callable( $fields[ $meta_key ]['auth_view_callback'] ) ) {
				$is_viewable = call_user_func( $fields[ $meta_key ]['auth_view_callback'], false, $meta_key, $post->ID, $current_user->ID );
				if ( ! $is_viewable ) {
					unset( $data['meta'][ $meta_key ] );
				}
			}
		}

		$response->set_data( $data );

		return $response;
	}
}
