<?php
/**
 * Regression tests for how job descriptions render.
 *
 * Descriptions can come from frontend submitters, so shortcodes are only expanded for listings
 * whose author can edit job listings, and `<track>` elements are not allowed in listing content.
 *
 * @package wp-job-manager/tests
 */

class Tests_Job_Description_Shortcodes extends WPJM_BaseTest {

	const TRACK_ELEMENT = '<track kind="captions" src="https://example.com/c.vtt" label="English">';

	/**
	 * @covers WP_Job_Manager_Post_Types::output_kses_post
	 */
	public function test_track_elements_in_shortcodes_are_not_rendered() {
		$job_id = $this->create_listing_by_role( 'subscriber', '[video src="https://example.com/a.mp4"]' . self::TRACK_ELEMENT . '[/video]' );

		$output = $this->render_single_listing( $job_id );

		$this->assertStringNotContainsString( '<track', $output, 'Tracks must not be rendered in listing content.' );
	}

	/**
	 * Tracks are dropped from video markup too, not only from the shortcode.
	 *
	 * @covers WP_Job_Manager_Post_Types::output_kses_post
	 */
	public function test_track_elements_in_markup_are_not_rendered() {
		$job_id = $this->create_listing_by_role( 'subscriber', '<video class="wp-video-shortcode" src="https://example.com/a.mp4">' . self::TRACK_ELEMENT . '</video>' );

		$output = $this->render_single_listing( $job_id );

		$this->assertStringNotContainsString( '<track', $output, 'Tracks must not be rendered in listing content.' );
	}

	/**
	 * @covers ::wpjm_get_the_job_description
	 */
	public function test_shortcodes_are_not_expanded_for_subscriber_listing() {
		$job_id = $this->create_listing_by_role( 'subscriber', 'Intro [video src="https://example.com/a.mp4"][/video]' );

		$output = $this->render_single_listing( $job_id );

		$this->assertStringContainsString( 'Intro', $output );
		$this->assertStringNotContainsString( 'wp-video-shortcode', $output, 'Shortcodes in a subscriber\'s description must not be expanded.' );
		$this->assertStringNotContainsString( '[video', $output, 'Shortcodes in a subscriber\'s description must be stripped.' );
	}

	/**
	 * @covers ::wpjm_get_the_job_description
	 */
	public function test_shortcodes_are_not_expanded_for_guest_listing() {
		$job_id = $this->create_listing( 0, 'Intro [video src="https://example.com/a.mp4"][/video]' );

		$output = $this->render_single_listing( $job_id );

		$this->assertStringNotContainsString( 'wp-video-shortcode', $output, 'Shortcodes in a guest description must not be expanded.' );
	}

	/**
	 * No-regression: listings written by users who can edit job listings keep their shortcodes.
	 *
	 * @covers ::wpjm_get_the_job_description
	 */
	public function test_shortcodes_are_expanded_for_admin_listing() {
		$job_id = $this->create_listing_by_role( 'administrator', 'Intro [video src="https://example.com/a.mp4"][/video]' );

		$output = $this->render_single_listing( $job_id );

		$this->assertStringContainsString( 'wp-video-shortcode', $output, 'Trusted authors must keep shortcode support.' );
	}

	/**
	 * Trust follows the listing-editing capability, not a role: a user granted it keeps shortcodes.
	 *
	 * @covers ::wpjm_get_the_job_description
	 */
	public function test_shortcodes_are_expanded_for_listing_editor() {
		$user = new WP_User( $this->factory->user->create( [ 'role' => 'subscriber' ] ) );
		$user->add_cap( \WP_Job_Manager_Post_Types::CAP_EDIT_LISTINGS );

		$job_id = $this->create_listing( $user->ID, 'Intro [video src="https://example.com/a.mp4"][/video]' );

		$output = $this->render_single_listing( $job_id );

		$this->assertStringContainsString( 'wp-video-shortcode', $output, 'Users who can edit listings must keep shortcode support.' );
	}

	/**
	 * @covers ::wpjm_get_the_job_description
	 */
	public function test_shortcode_support_can_be_enabled_by_filter() {
		$job_id = $this->create_listing_by_role( 'subscriber', 'Intro [video src="https://example.com/a.mp4"][/video]' );
		add_filter( 'job_manager_job_description_allow_shortcodes', '__return_true' );

		$output = $this->render_single_listing( $job_id );

		remove_filter( 'job_manager_job_description_allow_shortcodes', '__return_true' );
		$this->assertStringContainsString( 'wp-video-shortcode', $output );
	}

	/**
	 * Listing content shown outside the single listing template (search results, archives, query loops).
	 *
	 * @covers WP_Job_Manager_Post_Types::filter_listing_content
	 */
	public function test_subscriber_listing_content_in_a_list_is_restricted() {
		$job_id = $this->create_listing_by_role( 'subscriber', 'Intro [video src="https://example.com/a.mp4"]' . self::TRACK_ELEMENT . '[/video]' );

		$output = $this->render_listing_in_list( $job_id );

		$this->assertStringContainsString( 'Intro', $output );
		$this->assertStringNotContainsString( '<track', $output );
		$this->assertStringNotContainsString( 'wp-video-shortcode', $output );
	}

	/**
	 * @covers WP_Job_Manager_Post_Types::filter_listing_content
	 */
	public function test_subscriber_listing_content_in_rest_is_restricted() {
		$job_id = $this->create_listing_by_role( 'subscriber', 'Intro [video src="https://example.com/a.mp4"]' . self::TRACK_ELEMENT . '[/video]' );

		$this->login_as_admin();
		$response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/job-listings/' . $job_id ) );
		$rendered = $response->get_data()['content']['rendered'];

		$this->assertStringContainsString( 'Intro', $rendered );
		$this->assertStringNotContainsString( '<track', $rendered );
		$this->assertStringNotContainsString( 'wp-video-shortcode', $rendered );
	}

	/**
	 * Raw media markup from an untrusted author is removed on the single page.
	 *
	 * @covers WP_Job_Manager_Post_Types::restrict_listing_content
	 */
	public function test_raw_media_elements_from_subscriber_are_removed_on_single() {
		$job_id = $this->create_listing_by_role( 'subscriber', 'Intro <video class="wp-video-shortcode" src="https://example.com/a.mp4" poster="p.jpg"><source src="https://example.com/a.mp4" type="video/mp4"></video><audio src="https://example.com/a.mp3"></audio>' );

		$output = $this->render_single_listing( $job_id );

		$this->assertStringContainsString( 'Intro', $output );
		$this->assertStringNotContainsString( '<video', $output );
		$this->assertStringNotContainsString( '<audio', $output );
	}

	/**
	 * Raw media markup from an untrusted author is removed in list and REST contexts.
	 *
	 * @covers WP_Job_Manager_Post_Types::filter_listing_content
	 */
	public function test_raw_media_elements_from_subscriber_are_removed_in_list() {
		$job_id = $this->create_listing_by_role( 'subscriber', 'Intro <video class="wp-video-shortcode" src="https://example.com/a.mp4" poster="p.jpg"></video>' );

		$output = $this->render_listing_in_list( $job_id );

		$this->assertStringContainsString( 'Intro', $output );
		$this->assertStringNotContainsString( '<video', $output );
	}

	/**
	 * A bare media URL from an untrusted author is not turned into a media player.
	 *
	 * @covers WP_Job_Manager_Post_Types::restrict_listing_content
	 */
	public function test_bare_media_url_from_subscriber_is_not_embedded() {
		$job_id = $this->create_listing_by_role( 'subscriber', "Intro\n\nhttps://example.com/a.mp4\n" );

		$output = $this->render_single_listing( $job_id );

		$this->assertStringContainsString( 'Intro', $output );
		$this->assertStringNotContainsString( '<video', $output );
		$this->assertStringNotContainsString( '<source', $output );
		$this->assertStringNotContainsString( 'wp-video-shortcode', $output );
		$this->assertStringContainsString( 'href="https://example.com/a.mp4"', $output, 'The media URL should be kept as a link.' );
	}

	/**
	 * Block markup from an untrusted author is not rendered as blocks.
	 *
	 * @covers WP_Job_Manager_Post_Types::filter_listing_content
	 */
	public function test_block_markup_from_subscriber_is_not_rendered() {
		$job_id = $this->create_listing_by_role( 'subscriber', 'Intro <!-- wp:latest-posts {"postsToShow":3} /-->' );

		$output = $this->render_listing_in_list( $job_id );

		$this->assertStringContainsString( 'Intro', $output );
		$this->assertStringNotContainsString( 'wp:latest-posts', $output );
		$this->assertStringNotContainsString( 'wp-block-latest-posts', $output );
	}

	/**
	 * Control: listings by trusted authors keep their shortcodes in lists too.
	 *
	 * @covers WP_Job_Manager_Post_Types::filter_listing_content
	 */
	public function test_admin_listing_content_in_a_list_keeps_shortcodes() {
		$job_id = $this->create_listing_by_role( 'administrator', 'Intro [video src="https://example.com/a.mp4"][/video]' );

		$output = $this->render_listing_in_list( $job_id );

		$this->assertStringContainsString( 'wp-video-shortcode', $output );
	}

	/**
	 * Escaped shortcodes stay escaped text.
	 *
	 * @covers ::wpjm_get_the_job_description
	 */
	public function test_escaped_shortcode_is_not_expanded_for_subscriber_listing() {
		$job_id = $this->create_listing_by_role( 'subscriber', 'Intro [[video src="https://example.com/a.mp4"][/video]]' );

		$output = $this->render_single_listing( $job_id );

		$this->assertStringNotContainsString( 'wp-video-shortcode', $output );
	}

	/**
	 * Templates that pass the raw post content through `the_job_description` get the same treatment.
	 *
	 * @covers WP_Job_Manager_Post_Types::filter_job_description
	 */
	public function test_job_description_filter_restricts_raw_content() {
		$job_id = $this->create_listing_by_role( 'subscriber', 'Intro [video src="https://example.com/a.mp4"]' . self::TRACK_ELEMENT . '[/video]' );

		$this->go_to( get_permalink( $job_id ) );
		the_post();
		$output = do_shortcode( apply_filters( 'the_job_description', get_the_content() ) );
		wp_reset_postdata();

		$this->assertStringContainsString( 'Intro', $output );
		$this->assertStringNotContainsString( '<track', $output );
		$this->assertStringNotContainsString( 'wp-video-shortcode', $output );
	}

	/**
	 * Restricting the description must not remove the rest of the single listing template.
	 *
	 * @covers WP_Job_Manager_Post_Types::strip_untrusted_listing_media
	 */
	public function test_apply_button_is_kept_on_subscriber_listing() {
		$job_id = $this->create_listing_by_role( 'subscriber', 'Intro' );

		$output = $this->render_single_listing( $job_id );

		$this->assertStringContainsString( 'application_button', $output, 'The apply button must be kept.' );
	}

	/**
	 * @covers WP_Job_Manager_Post_Types::strip_untrusted_listing_media
	 */
	public function test_bare_media_url_from_subscriber_is_not_embedded_in_list() {
		$job_id = $this->create_listing_by_role( 'subscriber', "Intro\n\nhttps://example.com/a.mp4\n" );

		$output = $this->render_listing_in_list( $job_id );

		$this->assertStringContainsString( 'Intro', $output );
		$this->assertStringNotContainsString( '<video', $output );
	}

	/**
	 * @covers WP_Job_Manager_Post_Types::strip_untrusted_listing_media
	 */
	public function test_bare_media_url_from_subscriber_is_not_embedded_in_rest() {
		$job_id = $this->create_listing_by_role( 'subscriber', "Intro\n\nhttps://example.com/a.mp4\n" );

		$this->login_as_admin();
		$response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/job-listings/' . $job_id ) );
		$rendered = $response->get_data()['content']['rendered'];

		$this->assertStringContainsString( 'Intro', $rendered );
		$this->assertStringNotContainsString( '<video', $rendered );
	}

	/**
	 * Control: a bare media URL from a trusted author is still embedded.
	 *
	 * @covers WP_Job_Manager_Post_Types::strip_untrusted_listing_media
	 */
	public function test_bare_media_url_from_admin_is_embedded() {
		$job_id = $this->create_listing_by_role( 'administrator', "Intro\n\nhttps://example.com/a.mp4\n" );

		$this->assertStringContainsString( '<video', $this->render_single_listing( $job_id ) );
		$this->assertStringContainsString( '<video', $this->render_listing_in_list( $job_id ) );
	}

	/**
	 * Control: block markup from a trusted author is still rendered.
	 *
	 * @covers WP_Job_Manager_Post_Types::filter_listing_content
	 */
	public function test_block_markup_from_admin_is_rendered() {
		$job_id = $this->create_listing_by_role( 'administrator', 'Intro <!-- wp:latest-posts {"postsToShow":3} /-->' );

		$this->assertStringContainsString( 'wp-block-latest-posts', $this->render_listing_in_list( $job_id ) );
	}

	/**
	 * The description is restricted according to the listing being rendered, not the global post.
	 *
	 * @covers ::wpjm_get_the_job_description
	 */
	public function test_subscriber_description_is_restricted_when_global_post_is_trusted() {
		$admin_job_id = $this->create_listing_by_role( 'administrator', 'Admin listing' );
		$job_id       = $this->create_listing_by_role( 'subscriber', 'Intro [gallery]' );

		$GLOBALS['post'] = get_post( $admin_job_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$output          = wpjm_get_the_job_description( $job_id );
		unset( $GLOBALS['post'] );

		$this->assertStringContainsString( 'Intro', $output );
		$this->assertStringNotContainsString( '[gallery', $output );
	}

	/**
	 * @covers ::wpjm_get_the_job_description
	 */
	public function test_admin_description_is_kept_when_global_post_is_untrusted() {
		$subscriber_job_id = $this->create_listing_by_role( 'subscriber', 'Subscriber listing' );
		$job_id            = $this->create_listing_by_role( 'administrator', "Intro [gallery]\n\nhttps://example.com/a.mp4\n" );

		$GLOBALS['post'] = get_post( $subscriber_job_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$output          = wpjm_get_the_job_description( $job_id );
		unset( $GLOBALS['post'] );

		$this->assertStringContainsString( '[gallery]', $output, 'Trusted shortcodes must be kept for expansion.' );
		$this->assertStringContainsString( '[video', $output, 'Trusted media embeds must be kept.' );
	}

	/**
	 * The iframe sandbox that core adds to embeds from unknown providers must be kept.
	 *
	 * @covers WP_Job_Manager_Post_Types::output_kses_post
	 */
	public function test_iframe_sandbox_is_kept() {
		ob_start();
		WP_Job_Manager_Post_Types::output_kses_post( '<iframe src="https://example.com/embed" sandbox="allow-scripts" security="restricted"></iframe>' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'sandbox="allow-scripts"', $output );
		$this->assertStringContainsString( 'security="restricted"', $output );
	}

	/**
	 * The editor doesn't build media previews from an untrusted listing's content.
	 *
	 * @covers WP_Job_Manager_Post_Types::disable_untrusted_editor_previews
	 */
	public function test_editor_media_previews_are_disabled_for_subscriber_listing() {
		$job_id = $this->create_listing_by_role( 'subscriber', 'Intro [video src="https://example.com/a.mp4"][/video]' );

		$plugins = $this->get_editor_plugins( $job_id );

		$this->assertNotContains( 'wpview', $plugins );
		$this->assertContains( 'lists', $plugins );
	}

	/**
	 * Control: trusted listings keep their editor media previews.
	 *
	 * @covers WP_Job_Manager_Post_Types::disable_untrusted_editor_previews
	 */
	public function test_editor_media_previews_are_kept_for_admin_listing() {
		$job_id = $this->create_listing_by_role( 'administrator', 'Intro' );

		$this->assertContains( 'wpview', $this->get_editor_plugins( $job_id ) );
	}

	/**
	 * The company video player is built from a submitted URL, so it's removed for untrusted listings.
	 *
	 * @covers WP_Job_Manager_Post_Types::strip_untrusted_listing_media
	 */
	public function test_company_video_player_is_removed_for_subscriber_listing() {
		$job_id = $this->create_listing_by_role( 'subscriber', 'Intro' );
		update_post_meta( $job_id, '_company_video', 'https://example.com/company.mp4' );

		$output = $this->render_single_listing( $job_id );

		$this->assertStringNotContainsString( '<video', $output );
		$this->assertStringContainsString( 'href="https://example.com/company.mp4"', $output, 'The video URL should be kept as a link.' );
	}

	/**
	 * Control: trusted listings keep their company video player.
	 *
	 * @covers WP_Job_Manager_Post_Types::strip_untrusted_listing_media
	 */
	public function test_company_video_player_is_kept_for_admin_listing() {
		$job_id = $this->create_listing_by_role( 'administrator', 'Intro' );
		update_post_meta( $job_id, '_company_video', 'https://example.com/company.mp4' );

		$this->assertStringContainsString( '<video', $this->render_single_listing( $job_id ) );
	}

	/**
	 * Media tags are removed even from malformed markup.
	 *
	 * @dataProvider data_malformed_media_markup
	 * @covers WP_Job_Manager_Post_Types::strip_untrusted_listing_media
	 *
	 * @param string $markup Markup to strip.
	 */
	public function test_malformed_media_markup_is_not_left_as_tags( $markup ) {
		$job_id = $this->create_listing_by_role( 'subscriber', 'Intro' );

		$output = WP_Job_Manager_Post_Types::instance()->strip_untrusted_listing_media( 'Intro ' . $markup . ' <p>After</p>', get_post( $job_id ) );

		$this->assertDoesNotMatchRegularExpression( '#<\s*/?\s*(video|audio|source|track)\b#i', $output );
		$this->assertStringContainsString( '<p>After</p>', $output );
	}

	/**
	 * Custom elements whose names start with a media tag name are not media elements.
	 *
	 * @covers WP_Job_Manager_Post_Types::strip_untrusted_listing_media
	 */
	public function test_custom_elements_are_kept_for_subscriber_listing() {
		$job_id = $this->create_listing_by_role( 'subscriber', 'Intro' );
		$markup = '<video-player src="https://example.com/a.mp4"></video-player><audio-visualizer></audio-visualizer><track-list>x</track-list>';

		$output = WP_Job_Manager_Post_Types::instance()->strip_untrusted_listing_media( $markup, get_post( $job_id ) );

		$this->assertSame( $markup, $output );
	}

	public function data_malformed_media_markup() {
		return [
			'joined tags'           => [ '<vi<video>deo src="https://example.com/a.mp4">' ],
			'quote in unquoted attr' => [ '<track label=a"b src="https://example.com/c.vtt">' ],
			'unterminated tag'      => [ '<video src="https://example.com/a.mp4"' ],
			'upper case and slash'  => [ '<VIDEO/src="https://example.com/a.mp4"></VIDEO>' ],
			'attribute with >'      => [ '<audio title="a>b" src="https://example.com/a.mp3"></audio>' ],
		];
	}

	private function get_editor_plugins( $job_id ) {
		set_current_screen( 'job_listing' );
		$GLOBALS['post'] = get_post( $job_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$plugins = apply_filters( 'tiny_mce_plugins', [ 'lists', 'wpview' ], 'classic-block' );

		unset( $GLOBALS['post'] );
		set_current_screen( 'front' );

		return $plugins;
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

	private function create_listing_by_role( $role, $content ) {
		return $this->create_listing( $this->factory->user->create( [ 'role' => $role ] ), $content );
	}

	private function create_listing( $author_id, $content ) {
		// Mirror the frontend form, which stores the description through wp_kses_post().
		return $this->factory->job_listing->create(
			[
				'post_author'  => $author_id,
				'post_content' => wp_kses_post( $content ),
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
