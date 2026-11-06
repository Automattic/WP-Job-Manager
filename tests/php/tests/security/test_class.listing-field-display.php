<?php
/**
 * Regression tests for how listing text fields render.
 *
 * Company name, company tagline and location can be submitted from the frontend, and the
 * templates print them into content that is processed further. The fields are displayed as
 * the text that was entered unless the listing's author can edit job listings, matching how
 * the description is treated.
 *
 * @package wp-job-manager/tests
 */

class Tests_Listing_Field_Display extends WPJM_BaseTest {

	const SHORTCODE = 'wpjm_test_field_sc';
	const EXPANDED  = 'FIELD_SC_EXPANDED_SENTINEL';

	public function setUp(): void {
		parent::setUp();
		add_shortcode( self::SHORTCODE, [ __CLASS__, 'expand_test_shortcode' ] );
	}

	public function tearDown(): void {
		remove_shortcode( self::SHORTCODE );
		parent::tearDown();
	}

	public static function expand_test_shortcode() {
		return self::EXPANDED;
	}

	/**
	 * @covers ::the_company_name
	 */
	public function test_company_name_is_shown_as_text_on_subscriber_listing() {
		$job_id = $this->create_listing_by_role( 'subscriber', [ '_company_name' ] );

		$output = $this->render_single_listing( $job_id );

		$this->assertStringNotContainsString( self::EXPANDED, $output, 'Text in the company name must be displayed, not processed.' );
		$this->assertStringContainsString( '[' . self::SHORTCODE . ']', html_entity_decode( $output ), 'The company name text must be kept.' );
	}

	/**
	 * @covers ::the_company_tagline
	 */
	public function test_company_tagline_is_shown_as_text_on_subscriber_listing() {
		$job_id = $this->create_listing_by_role( 'subscriber', [ '_company_tagline' ] );

		$output = $this->render_single_listing( $job_id );

		$this->assertStringNotContainsString( self::EXPANDED, $output, 'Text in the company tagline must be displayed, not processed.' );
	}

	/**
	 * @covers ::the_job_location
	 */
	public function test_location_is_shown_as_text_on_subscriber_listing() {
		$job_id = $this->create_listing_by_role( 'subscriber', [ '_job_location' ] );

		$output = $this->render_single_listing( $job_id );

		$this->assertStringNotContainsString( self::EXPANDED, $output, 'Text in the location must be displayed, not processed.' );
	}

	/**
	 * Guest-submitted listings get the same treatment.
	 *
	 * @covers ::the_company_name
	 */
	public function test_fields_are_shown_as_text_on_guest_listing() {
		$job_id = $this->create_listing( 0, [ '_company_name', '_company_tagline', '_job_location' ] );

		$output = $this->render_single_listing( $job_id );

		$this->assertStringNotContainsString( self::EXPANDED, $output );
	}

	/**
	 * Control: fields on listings by trusted authors are processed as before.
	 *
	 * @covers ::the_company_name
	 */
	public function test_fields_keep_processing_on_admin_listing() {
		$job_id = $this->create_listing_by_role( 'administrator', [ '_company_name', '_company_tagline', '_job_location' ] );

		$output = $this->render_single_listing( $job_id );

		$this->assertStringContainsString( self::EXPANDED, $output, 'Trusted listings must render as before.' );
	}

	/**
	 * The plain-text location used by the list template gets the same treatment.
	 *
	 * @covers ::the_job_location
	 */
	public function test_plain_location_is_shown_as_text_for_subscriber_listing() {
		$job_id = $this->create_listing_by_role( 'subscriber', [ '_job_location' ] );

		ob_start();
		the_job_location( false, $job_id );
		$output = do_shortcode( ob_get_clean() );

		$this->assertStringNotContainsString( self::EXPANDED, $output );
		$this->assertStringContainsString( '[' . self::SHORTCODE . ']', html_entity_decode( $output ), 'The location text must be kept.' );
	}

	/**
	 * The returned company name gets the same treatment as the echoed one.
	 *
	 * @covers ::the_company_name
	 */
	public function test_returned_company_name_is_text_for_subscriber_listing() {
		$job_id = $this->create_listing_by_role( 'subscriber', [ '_company_name' ] );

		$output = do_shortcode( (string) the_company_name( '', '', false, $job_id ) );

		$this->assertStringNotContainsString( self::EXPANDED, $output );
	}

	/**
	 * Control: the returned company name of a trusted listing is unchanged.
	 *
	 * @covers ::the_company_name
	 */
	public function test_returned_company_name_is_unchanged_for_admin_listing() {
		$job_id = $this->create_listing_by_role( 'administrator', [ '_company_name' ] );

		$this->assertSame( 'Acme [' . self::SHORTCODE . '] Co', the_company_name( '', '', false, $job_id ) );
	}

	private function create_listing_by_role( $role, $fields_with_text ) {
		return $this->create_listing( $this->factory->user->create( [ 'role' => $role ] ), $fields_with_text );
	}

	/**
	 * Creates a listing whose given fields hold the test text; the other fields get plain values.
	 *
	 * @param int      $author_id        Listing author.
	 * @param string[] $fields_with_text Meta keys to fill with the test text.
	 * @return int
	 */
	private function create_listing( $author_id, $fields_with_text ) {
		$field_text = 'Acme [' . self::SHORTCODE . '] Co';
		$meta       = [
			'_company_name'    => 'Acme',
			'_company_tagline' => 'A fine company',
			'_job_location'    => 'Sydney',
		];

		foreach ( $fields_with_text as $meta_key ) {
			$meta[ $meta_key ] = $field_text;
		}

		return $this->factory->job_listing->create(
			[
				'post_author' => $author_id,
				'meta_input'  => $meta,
			]
		);
	}

	private function render_single_listing( $job_id ) {
		global $wp_query;

		$this->login_as_admin();
		$wp_query = new WP_Query( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			[
				'p'         => $job_id,
				'post_type' => \WP_Job_Manager_Post_Types::PT_LISTING,
			]
		);

		$output = '';
		while ( $wp_query->have_posts() ) {
			$wp_query->the_post();
			ob_start();
			the_content();
			$output .= ob_get_clean();
		}
		wp_reset_postdata();

		$this->assertStringContainsString( '<div class="single_job_listing"', $output, 'The listing template should have rendered.' );

		return $output;
	}
}
