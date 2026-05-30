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
		add_filter( 'rest_request_before_callbacks', [ __CLASS__, 'gate_view_capability_for_single' ], 10, 3 );
	}

	/**
	 * Returns 404 for GET requests to a single job listing when the current user is
	 * denied by the `job_manager_view_job_listing_capability` option. Mirrors WP core's
	 * "post not found" shape so the existence of restricted listings is not revealed.
	 *
	 * The view-capability check is the only gate. For view-cap-*passing* users on
	 * password-protected listings the request continues and WP core's controller +
	 * `prepare_job_listing()` produce the password contract (200 + `content.protected`)
	 * downstream. View-cap-*failing* users always get 404, even on password-protected
	 * listings — otherwise the password envelope would itself reveal that the listing
	 * exists at that ID. Author and `preview` short-circuits live inside
	 * `job_manager_user_can_view_job_listing()`.
	 *
	 * @param mixed           $response Result from the dispatched request, prior to invoking the callback.
	 * @param array           $handler  Route handler used for the request.
	 * @param WP_REST_Request $request  Request used to generate the response.
	 * @return mixed
	 */
	public static function gate_view_capability_for_single( $response, $handler, $request ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		// HEAD falls back to the GET handler in WP_REST_Server but keeps `HEAD` as the method;
		// without HEAD coverage a status-code probe (200 empty body vs 404) could distinguish
		// a restricted listing from a missing one.
		if ( ! in_array( $request->get_method(), [ 'GET', 'HEAD' ], true ) ) {
			return $response;
		}
		// Match the item route and its children (revisions, autosaves) — all of them can
		// surface listing body data and must be gated on the parent post's view capability.
		if ( ! preg_match( '#^/wp/v2/job-listings/(?P<id>\d+)(?:/[^?]*)?$#', (string) $request->get_route(), $matches ) ) {
			return $response;
		}
		$post_id = absint( $matches['id'] );
		if ( ! $post_id ) {
			return $response;
		}
		$post = get_post( $post_id );
		if ( ! $post || WP_Job_Manager_Post_Types::PT_LISTING !== $post->post_type ) {
			return $response;
		}
		if ( job_manager_user_can_view_job_listing( $post_id ) ) {
			return $response;
		}

		return new WP_Error(
			'rest_post_invalid_id',
			// String mirrors WP core's WP_REST_Posts_Controller so a denied viewer cannot
			// distinguish this 404 from a missing-post 404.
			__( 'Invalid post ID.' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			[ 'status' => 404 ]
		);
	}

	/**
	 * Excludes filled job listings from REST API query results, and short-circuits the query when
	 * the requester does not have the browse-listings capability.
	 *
	 * @param array           $args    Array of query arguments.
	 * @param WP_REST_Request $request The REST API request.
	 * @return array
	 */
	public static function exclude_filled_from_query( $args, $request ) {
		// Browse-capability gate — match the same denial the [jobs] shortcode applies, but without surfacing a 403.
		if ( ! job_manager_user_can_browse_job_listings() ) {
			$args['post__in'] = [ 0 ];
			return $args;
		}

		// Password-protected listings are excluded from REST collections regardless of post-type config.
		$args['has_password'] = false;

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

		// View-capability denials are normally short-circuited to 404 in
		// gate_view_capability_for_single() before we reach this filter. Keep the blanking
		// path as defense-in-depth for any code path that still reaches here (collection
		// responses, third-party callers re-running this filter), and for password-protected
		// listings where the WP contract is 200 + content.protected so clients can render a
		// password form. Title / link / slug / featured-media references can themselves carry
		// sensitive information so we blank them too.
		$is_password_blocked = post_password_required( $post );
		$is_viewcap_blocked  = ! job_manager_user_can_view_job_listing( $post->ID );
		$is_blocked          = $is_password_blocked || $is_viewcap_blocked;

		// An edit-capable user hitting a *password-only* protected listing legitimately needs
		// the raw fields and editor metadata to operate Gutenberg (WP core's controller permits
		// the request precisely because of edit_post). WP core itself still blanks
		// `content.rendered`, which is enough for the password contract. View-capability
		// denials do NOT get this bypass — they are the harder gate.
		$bypass_for_editor = $is_password_blocked && ! $is_viewcap_blocked
			&& current_user_can( 'edit_post', $post->ID );

		if ( $is_blocked && ! $bypass_for_editor ) {
			if ( isset( $data['title']['rendered'] ) ) {
				$data['title']['rendered'] = '';
			}
			if ( isset( $data['title']['raw'] ) ) {
				$data['title']['raw'] = '';
			}
			if ( isset( $data['content']['rendered'] ) ) {
				$data['content']['rendered'] = '';
			}
			if ( isset( $data['content']['raw'] ) ) {
				$data['content']['raw'] = '';
			}
			if ( isset( $data['content'] ) && is_array( $data['content'] ) ) {
				$data['content']['protected'] = true;
			}
			if ( isset( $data['excerpt']['rendered'] ) ) {
				$data['excerpt']['rendered'] = '';
			}
			if ( isset( $data['excerpt']['raw'] ) ) {
				$data['excerpt']['raw'] = '';
			}
			if ( array_key_exists( 'link', $data ) ) {
				unset( $data['link'] );
			}
			if ( array_key_exists( 'slug', $data ) ) {
				$data['slug'] = '';
			}
			if ( array_key_exists( 'featured_media', $data ) ) {
				$data['featured_media'] = 0;
			}
			// Links live on WP_REST_Response's private $links property and are merged into the
			// serialized `_links` block later by WP_REST_Server::response_to_data(); they are not
			// present in $data at this point, so remove_link() is the only effective way to drop
			// the featured-media link before serialization.
			$response->remove_link( 'https://api.w.org/featuredmedia' );
			$data['meta'] = [];
			$response->set_data( $data );

			return $response;
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
