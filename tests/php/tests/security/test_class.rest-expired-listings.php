<?php
/**
 * Regression tests: the REST API withholds expired listing content the way the single
 * listing view does.
 *
 * The `expired` status is registered public, so core's REST permission checks allow
 * reading it. The single view replaces an expired listing's body with an "expired"
 * notice when hiding expired content is on (the default); the REST item and the
 * collection route must not serve more than the web does.
 *
 * @package wp-job-manager/tests
 */

class Tests_REST_Expired_Listings extends WPJM_REST_TestCase {

	const CONTENT_SENTINEL = 'SENTINEL_EXPIRED_BODY';
	const EMAIL_SENTINEL   = 'expired-apply@example.test';

	public function setUp(): void {
		parent::setUp();
		// `WPJM_REST_TestCase::setUp` re-registers the post type but not its meta fields,
		// which leaves $wp_meta_keys without the job_listing meta entries — REST then
		// returns an empty `meta` block. Mirror the workaround used by
		// tests/php/tests/security/test_class.password-protected-listing-rest.php.
		global $wp_meta_keys;
		unset( $wp_meta_keys['post'][ \WP_Job_Manager_Post_Types::PT_LISTING ] );
		WP_Job_Manager_Post_Types::instance()->register_meta_fields();
	}

	private function create_expired_listing() {
		return $this->factory->job_listing->create(
			[
				'post_status'  => 'expired',
				'post_content' => self::CONTENT_SENTINEL,
				'meta_input'   => [ '_application' => self::EMAIL_SENTINEL ],
			]
		);
	}

	private function get_item( $id ) {
		return rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/job-listings/' . $id ) );
	}

	/**
	 * @covers WP_Job_Manager_REST_API::prepare_job_listing
	 */
	public function test_expired_item_body_is_withheld_from_visitors() {
		$listing_id = $this->create_expired_listing();
		$this->logout();

		$response = $this->get_item( $listing_id );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'The expired listing page exists on the web, so the item itself stays readable.' );
		$this->assertStringNotContainsString( self::CONTENT_SENTINEL, wp_json_encode( $data ), 'The listing body must be withheld.' );
		$this->assertStringNotContainsString( self::EMAIL_SENTINEL, wp_json_encode( $data ), 'Listing meta must be withheld.' );
		$this->assertNotEmpty( $data['title']['rendered'], 'The title stays visible, as on the web.' );
	}

	/**
	 * Control: a visitor who may edit the listing reads it in full.
	 *
	 * @covers WP_Job_Manager_REST_API::prepare_job_listing
	 */
	public function test_expired_item_body_is_shown_to_an_editor() {
		$listing_id = $this->create_expired_listing();
		$this->login_as_admin();

		$data = $this->get_item( $listing_id )->get_data();

		$this->assertStringContainsString( self::CONTENT_SENTINEL, $data['content']['rendered'] );
	}

	/**
	 * Control: a site that chose to show expired content keeps showing it.
	 *
	 * @covers WP_Job_Manager_REST_API::prepare_job_listing
	 */
	public function test_expired_item_body_respects_the_site_option() {
		update_option( 'job_manager_hide_expired_content', 0 );
		$listing_id = $this->create_expired_listing();
		$this->logout();

		$data = $this->get_item( $listing_id )->get_data();
		delete_option( 'job_manager_hide_expired_content' );

		$this->assertStringContainsString( self::CONTENT_SENTINEL, $data['content']['rendered'] );
	}

	/**
	 * Published listings are unaffected.
	 *
	 * @covers WP_Job_Manager_REST_API::prepare_job_listing
	 */
	public function test_published_item_body_stays_readable() {
		$listing_id = $this->factory->job_listing->create( [ 'post_content' => self::CONTENT_SENTINEL ] );
		$this->logout();

		$data = $this->get_item( $listing_id )->get_data();

		$this->assertStringContainsString( self::CONTENT_SENTINEL, $data['content']['rendered'] );
	}

	/**
	 * The collection route matches the web listing queries: expired listings are left out
	 * for visitors, even when requested by status.
	 *
	 * @covers WP_Job_Manager_REST_API::exclude_filled_from_query
	 */
	public function test_collection_excludes_expired_for_visitors() {
		$listing_id = $this->create_expired_listing();
		$this->logout();

		$request = new WP_REST_Request( 'GET', '/wp/v2/job-listings' );
		$request->set_param( 'status', 'expired' );
		$response = rest_do_request( $request );

		// A denied request (an error status) withholds the listing just as well as an
		// empty collection does; both must not contain the expired listing.
		$ids = [];
		if ( ! $response->is_error() && is_array( $response->get_data() ) ) {
			$ids = wp_list_pluck( $response->get_data(), 'id' );
		}

		$this->assertNotContains( $listing_id, $ids, 'Expired listings must not be served to visitors through the collection route.' );
	}

	/**
	 * Control: a listings editor can still query expired listings through the collection.
	 *
	 * @covers WP_Job_Manager_REST_API::exclude_filled_from_query
	 */
	public function test_collection_serves_expired_to_an_editor() {
		$listing_id = $this->create_expired_listing();
		$this->login_as_admin();

		$request = new WP_REST_Request( 'GET', '/wp/v2/job-listings' );
		$request->set_param( 'status', 'expired' );
		$response = rest_do_request( $request );

		$this->assertContains( $listing_id, wp_list_pluck( $response->get_data(), 'id' ) );
	}
}
