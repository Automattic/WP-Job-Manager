<?php
/**
 * Regression tests: listing content by authors who can't post unfiltered HTML does not
 * render author-written iframes.
 *
 * An iframe in submitter-controlled content puts a page of the submitter's choosing inside
 * the listing — including in front of a moderator opening a pending listing. Embeds produced
 * by oEmbed for trusted authors, and the rest of the restricted allowlist, are unaffected.
 *
 * @package wp-job-manager/tests
 */

class Tests_Restricted_Listing_Iframes extends WPJM_BaseTest {

	const IFRAME = '<iframe src="https://example.com/external-page" width="400" height="300"></iframe>';

	/**
	 * @covers WP_Job_Manager_Post_Types::restrict_listing_content
	 */
	public function test_iframe_from_subscriber_is_removed_on_single() {
		$job_id = $this->create_listing_by_role( 'subscriber', 'Intro ' . self::IFRAME );

		$output = $this->render_single_listing( $job_id );

		$this->assertStringContainsString( 'Intro', $output );
		$this->assertStringNotContainsString( '<iframe', $output, 'An untrusted author\'s iframe must not be rendered.' );
	}

	/**
	 * @covers WP_Job_Manager_Post_Types::filter_listing_content
	 */
	public function test_iframe_from_subscriber_is_removed_in_list_and_rest() {
		$job_id = $this->create_listing_by_role( 'subscriber', 'Intro ' . self::IFRAME );

		$this->login_as_admin();
		$response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/job-listings/' . $job_id ) );
		$rendered = $response->get_data()['content']['rendered'];

		$this->assertStringContainsString( 'Intro', $rendered );
		$this->assertStringNotContainsString( '<iframe', $rendered );
	}

	/**
	 * Editors don't have `edit_job_listings` by default, so their listings are restricted too.
	 *
	 * @covers WP_Job_Manager_Post_Types::restrict_listing_content
	 */
	public function test_iframe_from_editor_is_removed_on_single() {
		$job_id = $this->create_listing_by_role( 'editor', 'Intro ' . self::IFRAME );

		$output = $this->render_single_listing( $job_id );

		$this->assertStringNotContainsString( '<iframe', $output );
	}

	/**
	 * Control: a trusted author's iframe still renders where WPJM leaves their content alone.
	 *
	 * The single listing view is not that place: wpjm_get_the_job_description() has passed
	 * every description through wp_kses_post() since 2.4.9, which allows no iframes, for any
	 * author. List and REST output of a trusted listing is untouched by the plugin.
	 *
	 * @covers WP_Job_Manager_Post_Types::filter_listing_content
	 */
	public function test_iframe_from_admin_is_kept_in_list() {
		$job_id = $this->create_listing_by_role( 'administrator', 'Intro ' . self::IFRAME );
		$this->assertStringContainsString( '<iframe', get_post( $job_id )->post_content, 'The iframe must reach the stored content for this test to mean anything.' );

		$output = $this->render_listing_in_list( $job_id );

		$this->assertStringContainsString( '<iframe', $output, 'Trusted authors keep their iframes in list output.' );
	}

	/**
	 * Control: the rest of the restricted allowlist is untouched.
	 *
	 * @covers WP_Job_Manager_Post_Types::restrict_listing_content
	 */
	public function test_other_allowed_markup_from_subscriber_is_kept() {
		$job_id = $this->create_listing_by_role( 'subscriber', '<p>Intro <strong>bold</strong> <a href="https://example.com/">link</a></p>' );

		$output = $this->render_single_listing( $job_id );

		$this->assertStringContainsString( '<strong>bold</strong>', $output );
		$this->assertStringContainsString( 'href="https://example.com/"', $output );
	}

	private function create_listing_by_role( $role, $content ) {
		// Stored raw: an iframe reaches the content of an author without `edit_job_listings`
		// through writes kses doesn't filter — a single-site Editor in wp-admin has
		// `unfiltered_html`, and existing content predates filtering. Save with such a user
		// current, since the test environment kses-filters anonymous inserts.
		$previous_user = get_current_user_id();
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );

		$job_id = $this->factory->job_listing->create(
			[
				'post_author'  => $this->factory->user->create( [ 'role' => $role ] ),
				'post_content' => $content,
			]
		);

		wp_set_current_user( $previous_user );

		return $job_id;
	}

	private function render_listing_in_list( $job_id ) {
		global $wp_query;

		$wp_query = new WP_Query( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			[
				'post_type' => \WP_Job_Manager_Post_Types::PT_LISTING,
				'post__in'  => [ $job_id ],
			]
		);

		$this->assertFalse( is_singular(), 'The listing should be rendered outside the single listing template.' );

		$output = '';
		while ( $wp_query->have_posts() ) {
			$wp_query->the_post();
			ob_start();
			the_content();
			$output .= ob_get_clean();
		}
		wp_reset_postdata();

		return $output;
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
