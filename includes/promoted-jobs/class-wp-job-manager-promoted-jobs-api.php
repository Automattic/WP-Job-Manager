<?php
/**
 * File containing the class WP_Job_Manager_Promoted_Jobs_API.
 *
 * @package wp-job-manager
 */

/**
 * Handles functionality related to the Promoted Jobs REST API.
 *
 * @since $$next-version$$
 */
class WP_Job_Manager_Promoted_Jobs_API {

	/**
	 * The namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE = 'wpjm-internal/v1';

	/**
	 * Rest base for the current object.
	 *
	 * @var string
	 */
	private const REST_BASE = '/promoted-jobs';

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			self::REST_BASE,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => '__return_true',
				],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' .
			$this->rest_base . '/(?P<id>[\d]+)',
			[
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_job_status' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'id'     => [
							'validate_callback' => function( $param ) {
								return is_numeric( $param );
							},
						],
						'status' => [
							'validate_callback' => function( $param ) {
								return is_bool( $param );
							},
						],
					],
				],
			]
		);
	}

	/**
	 * Get all promoted jobs.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items() {
		$args = [
			'post_type'           => 'job_listing',
			'post_status'         => 'publish',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'meta_query'          => [
				[
					'key'     => WP_Job_Manager_Promoted_Jobs::META_KEY,
					'value'   => '1',
					'compare' => '=',
				],
			],
		];

		$items = get_posts( $args );

		if ( empty( $items ) ) {
			return rest_ensure_response( $items );
		}

		$data = array_map( [ $this, 'prepare_item_for_response' ], $items );

		return new WP_REST_Response( [ 'jobs' => $data ], 200 );
	}

	/**
	 * Prepare the item for the REST response
	 *
	 * @param WP_Post $item WordPress representation of the item.
	 * @return array The response
	 */
	private function prepare_item_for_response( WP_Post $item ) {
		$terms = get_the_terms( $item->ID, 'job_listing_type' );

		$terms_array = [];
		foreach ( $terms as $term ) {
			$terms_array[] = $term->slug;
		}

		return [
			'id'           => (string) $item->ID,
			'title'        => $item->post_title,
			'description'  => $item->post_content,
			'permalink'    => get_permalink( $item ),
			'location'     => get_post_meta( $item->ID, '_job_location', true ),
			'company_name' => get_post_meta( $item->ID, '_company_name', true ),
			'is_remote'    => (bool) get_post_meta( $item->ID, '_remote_position', true ),
			'job_type'     => $terms_array,
			'salary'       => [
				'salary_amount'   => get_post_meta( $item->ID, '_job_salary', true ),
				'salary_currency' => get_post_meta( $item->ID, '_job_salary_currency', true ),
				'salary_unit'     => get_post_meta( $item->ID, '_job_salary_unit', true ),
			],
		];
	}

	/**
	 * Update the promoted job status.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_job_status( $request ) {
		$post_id = $request->get_param( 'id' );
		$status  = $request->get_param( 'status' );
		$post    = get_post( $post_id );

		if ( empty( $post ) ) {
			return new WP_Error( 'not_found', __( 'The promoted job was not found', 'wp-job-manager' ), [ 'status' => 404 ] );
		}

		$result = update_post_meta( $post_id, WP_Job_Manager_Promoted_Jobs::META_KEY, $status );
		return new WP_REST_Response(
			[
				'data'    => $result,
				'message' => 'Promoted job status updated',
			],
			200
		);
	}
}
