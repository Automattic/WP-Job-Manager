<?php
/**
 * Regression tests covering the REST `job_listing` endpoints and the AJAX listing endpoint
 * for password-protected listings: meta and identifying top-level fields must not surface,
 * and the AJAX/REST collections must exclude protected listings.
 *
 * @package wp-job-manager/tests
 */

class Tests_Password_Protected_Listing_REST extends WPJM_REST_TestCase {

	public function setUp(): void {
		parent::setUp();
		// `WPJM_REST_TestCase::setUp` re-registers the post type but not its meta fields,
		// which leaves $wp_meta_keys without the job_listing meta entries — REST then
		// returns an empty `meta` block. Mirror the workaround used by
		// tests/php/tests/includes/rest-api/test_class.wp-job-manager-job-listings.php.
		global $wp_meta_keys;
		unset( $wp_meta_keys['post'][ \WP_Job_Manager_Post_Types::PT_LISTING ] );
		WP_Job_Manager_Post_Types::instance()->register_meta_fields();
	}

	/**
	 * @covers WP_Job_Manager_REST_API::prepare_job_listing
	 */
	public function test_rest_single_blanks_top_level_fields_for_password_protected() {
		$post_id       = $this->factory->job_listing->create(
			[
				'post_password' => 'secret',
				'post_title'    => '[CONFIDENTIAL] Senior Engineer at InsiderCo',
			]
		);
		// Attach a dummy thumbnail so core's REST controller emits the `wp:featuredmedia` link;
		// without it the link is never registered and the assertion below would pass trivially.
		$attachment_id = $this->factory->post->create( [ 'post_type' => 'attachment' ] );
		set_post_thumbnail( $post_id, $attachment_id );
		$this->logout();

		$response = $this->get( "/wp/v2/job-listings/{$post_id}" );
		$this->assertResponseStatus( $response, 200 );
		$data = $response->get_data();

		$this->assertSame( '', $data['title']['rendered'], 'Title must be blanked.' );
		$this->assertArrayNotHasKey( 'link', $data, 'Link must be removed.' );
		$this->assertSame( '', $data['slug'] ?? '', 'Slug must be blanked.' );
		$this->assertSame( 0, (int) ( $data['featured_media'] ?? 0 ), 'Featured media must be cleared.' );
		$this->assertArrayNotHasKey(
			'https://api.w.org/featuredmedia',
			$response->get_links(),
			'Featured-media link relation must be removed so ?_embed cannot leak the attachment.'
		);
		$this->assertTrue( $data['content']['protected'] );
	}

	/**
	 * @covers WP_Job_Manager_REST_API::prepare_job_listing
	 * @covers WP_Job_Manager_Post_Types::auth_check_can_view_job_listing
	 */
	public function test_rest_single_strips_sensitive_meta_for_password_protected() {
		$post_id = $this->factory->job_listing->create(
			[
				'post_password' => 'secret',
				'meta_input'    => [
					'_application'      => 'ceo-secret@insiderco.example',
					'_company_name'     => 'InsiderCo Holdings',
					'_company_tagline'  => 'private-tagline-PROJECT-MAGENTA',
					'_company_website'  => 'https://insiderco.example/private-portal',
					'_job_location'     => 'San Francisco - SECRET-WAREHOUSE-7',
				],
			]
		);
		$this->logout();

		$response = $this->get( "/wp/v2/job-listings/{$post_id}" );
		$this->assertResponseStatus( $response, 200 );
		$meta = $response->get_data()['meta'] ?? [ 'unset' => true ];

		// `prepare_job_listing` short-circuits the entire meta block when the listing is
		// password-protected; an empty array is the contract.
		$this->assertSame( [], $meta, 'All meta must be stripped for password-protected listings.' );
	}

	/**
	 * @covers WP_Job_Manager_REST_API::exclude_filled_from_query
	 *
	 * The REST collection must not return password-protected listings to anonymous viewers,
	 * regardless of whether browse capability is configured.
	 */
	public function test_rest_collection_excludes_password_protected() {
		$protected = $this->factory->job_listing->create( [ 'post_password' => 'secret' ] );
		$public    = $this->factory->job_listing->create();
		$this->logout();

		$response = $this->get( '/wp/v2/job-listings' );
		$ids      = wp_list_pluck( $response->get_data(), 'id' );

		$this->assertNotContains( $protected, $ids );
		$this->assertContains( $public, $ids );
	}

	/**
	 * @covers ::get_job_listings
	 * @covers WP_Job_Manager_Ajax::get_listings
	 *
	 * The AJAX endpoint must not surface password-protected listings even when called
	 * with no keyword.
	 */
	public function test_ajax_no_keyword_excludes_password_protected() {
		$protected = $this->factory->job_listing->create( [ 'post_password' => 'secret' ] );
		$public    = $this->factory->job_listing->create();
		$this->logout();

		add_filter( 'wp_die_ajax_handler', [ $this, 'return_do_not_die' ] );
		ob_start();
		WP_Job_Manager_Ajax::instance()->get_listings();
		$payload = json_decode( (string) ob_get_clean(), true );
		remove_filter( 'wp_die_ajax_handler', [ $this, 'return_do_not_die' ] );

		$this->assertIsArray( $payload );
		$html = (string) ( $payload['html'] ?? '' );
		$this->assertStringNotContainsString( "post-{$protected}", $html );
		$this->assertStringContainsString( "post-{$public}", $html );
	}

	/**
	 * @covers WP_Job_Manager_REST_API::prepare_job_listing
	 *
	 * Sanity / no regression: a normal published listing returns its title, link and a
	 * populated meta block when the viewer is authorized. Mirrors the meta-presence pattern
	 * used by tests/php/tests/includes/rest-api/test_class.wp-job-manager-job-listings.php.
	 */
	public function test_rest_single_returns_full_data_for_normal_listing() {
		$post_id = $this->factory->job_listing->create(
			[ 'post_title' => 'Public Junior Coffee Taster' ]
		);
		$this->login_as_admin();

		$response = $this->get( "/wp/v2/job-listings/{$post_id}" );
		$this->assertResponseStatus( $response, 200 );
		$data = $response->get_data();

		$this->assertSame( 'Public Junior Coffee Taster', $data['title']['rendered'] );
		$this->assertArrayHasKey( 'link', $data );
		$this->assertNotEmpty( $data['meta'], 'Normal listings must still expose meta to authorized viewers.' );
		$this->assertArrayHasKey( '_company_name', $data['meta'] );
		$this->assertArrayHasKey( '_job_location', $data['meta'] );
	}

	/**
	 * @covers WP_Job_Manager_Post_Types::gate_feed_query_for_listings
	 *
	 * The default core RSS feed scoped to `post_type=job_listing` (distinct from the custom
	 * `job_feed` slug) must exclude password-protected listings. The custom job_feed already
	 * sets `has_password=false` explicitly; this test covers the `pre_get_posts` gate that
	 * applies to every other core feed slug routed at the post type.
	 */
	public function test_default_feed_query_excludes_password_protected() {
		$query              = new \WP_Query();
		$query->is_feed     = true;
		$query->set( 'post_type', \WP_Job_Manager_Post_Types::PT_LISTING );
		// `is_main_query()` returns true only when $query === $wp_the_query.
		$GLOBALS['wp_the_query'] = $query;

		\WP_Job_Manager_Post_Types::instance()->gate_feed_query_for_listings( $query );

		$this->assertFalse(
			$query->get( 'has_password' ),
			'Default feed query for job_listing must set has_password=false.'
		);
	}

	/**
	 * @covers WP_Job_Manager_Post_Types::gate_feed_query_for_listings
	 *
	 * When browse capability denies the viewer, the default feed query for `job_listing`
	 * must short-circuit to an empty result (matching the AJAX / REST / job_feed gate).
	 */
	public function test_default_feed_query_short_circuits_when_browse_cap_denies() {
		update_option( 'job_manager_browse_job_listings_capability', [ 'manage_options' ] );
		$this->logout();

		$query              = new \WP_Query();
		$query->is_feed     = true;
		$query->set( 'post_type', \WP_Job_Manager_Post_Types::PT_LISTING );
		$GLOBALS['wp_the_query'] = $query;

		\WP_Job_Manager_Post_Types::instance()->gate_feed_query_for_listings( $query );

		$this->assertSame(
			[ 0 ],
			$query->get( 'post__in' ),
			'Browse-cap-denied feed query must be forced to an empty result-set.'
		);

		delete_option( 'job_manager_browse_job_listings_capability' );
	}

	/**
	 * @covers WP_Job_Manager_Post_Types::gate_feed_query_for_listings
	 *
	 * Sanity: non-job-listing feed queries (e.g. the standard post feed) are not touched.
	 */
	public function test_default_feed_query_leaves_non_job_listing_queries_alone() {
		$query              = new \WP_Query();
		$query->is_feed     = true;
		$query->set( 'post_type', 'post' );
		$GLOBALS['wp_the_query'] = $query;

		\WP_Job_Manager_Post_Types::instance()->gate_feed_query_for_listings( $query );

		$this->assertNotSame( false, $query->get( 'has_password' ) );
	}
}
