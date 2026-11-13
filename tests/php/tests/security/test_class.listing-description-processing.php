<?php
/**
 * Regression tests for what happens to an untrusted listing's description after it has been
 * restricted: the content goes on through the HTML filter, embeds and shortcode expansion,
 * and has to come out of all of them still restricted.
 *
 * @package wp-job-manager/tests
 */

class Tests_Listing_Description_Processing extends WPJM_BaseTest {

	const SHORTCODE = 'wpjm_test_description_sc';

	/**
	 * How many times the test shortcode ran.
	 *
	 * @var int
	 */
	private $ran = 0;

	public function setUp(): void {
		parent::setUp();
		add_shortcode(
			self::SHORTCODE,
			function () {
				++$this->ran;

				return 'DESCRIPTION_SC_RAN';
			}
		);
	}

	public function tearDown(): void {
		remove_shortcode( self::SHORTCODE );
		remove_all_filters( 'pre_http_request' );
		parent::tearDown();
	}

	public function data_brackets_in_tags() {
		$sc = '[' . self::SHORTCODE . ']';

		return [
			'link address'               => [ '<a href="' . $sc . '">x</a>' ],
			'inside a link address'      => [ '<a href="https://example.com/' . $sc . '">x</a>' ],
			'title'                      => [ '<a title="' . $sc . '" href="#">x</a>' ],
			'image description'          => [ '<img alt="' . $sc . '" src="x.png">' ],
			'class'                      => [ '<p class="' . $sc . '">x</p>' ],
			'decimal reference'          => [ '<a title="&#91;' . self::SHORTCODE . '&#93;" href="#">x</a>' ],
			'padded decimal reference'   => [ '<a title="&#091;' . self::SHORTCODE . '&#093;" href="#">x</a>' ],
			'hex reference'              => [ '<a title="&#x5b;' . self::SHORTCODE . '&#x5D;" href="#">x</a>' ],
			'named reference'            => [ '<a title="&lbrack;' . self::SHORTCODE . '&rbrack;" href="#">x</a>' ],
			'other named reference'      => [ '<a title="&lsqb;' . self::SHORTCODE . '&rsqb;" href="#">x</a>' ],
			'reference with no semicolon' => [ '<a title="&#91' . self::SHORTCODE . '&#93" href="#">x</a>' ],
			'single quoted'              => [ "<a title='" . $sc . "' href='#'>x</a>" ],
			'enclosing shortcode'        => [ '<a title="[' . self::SHORTCODE . ']t[/' . self::SHORTCODE . ']" href="#">x</a>' ],
		];
	}

	/**
	 * Whatever form a bracket takes inside a tag, no attribute may hold one afterwards, and
	 * that must still be so after the HTML filter has decoded and rewritten the attributes.
	 *
	 * @dataProvider data_brackets_in_tags
	 * @covers WP_Job_Manager_Post_Types::restrict_content_html
	 */
	public function test_no_bracket_is_left_in_an_attribute( $content ) {
		$restricted = WP_Job_Manager_Post_Types::restrict_content_html( $content );

		$this->assertSame( [], $this->find_brackets_in_attributes( $restricted ) );
		$this->assertSame( [], $this->find_brackets_in_attributes( wp_kses_post( $restricted ) ) );
		$this->assertSame( $restricted, WP_Job_Manager_Post_Types::restrict_content_html( $restricted ), 'Restricting content twice must not change it.' );
	}

	/**
	 * @dataProvider data_brackets_in_tags
	 * @covers WP_Job_Manager_Post_Types::restrict_content_html
	 */
	public function test_shortcode_in_an_attribute_is_not_run( $content ) {
		$job_id = $this->create_listing( 'subscriber', 'Before ' . $content . ' after' );

		$single = $this->render_single_listing( $job_id );
		do_shortcode( $single );
		$shortcode = do_shortcode( '[job id="' . $job_id . '"]' );
		do_shortcode( $shortcode );

		$this->assertSame( 0, $this->ran );
		$this->assertStringNotContainsString( 'DESCRIPTION_SC_RAN', $single . $shortcode );
	}

	/**
	 * Control: a trusted listing's description is processed as before.
	 */
	public function test_shortcode_in_an_attribute_still_runs_for_admin_listing() {
		$job_id = $this->create_listing( 'administrator', '<a title="[' . self::SHORTCODE . ']" href="#">x</a>' );

		$single = $this->render_single_listing( $job_id );

		$this->assertGreaterThan( 0, $this->ran );
		$this->assertStringContainsString( 'title="DESCRIPTION_SC_RAN"', $single );
	}

	/**
	 * A bracket in a link address still leads to the same place.
	 *
	 * @covers WP_Job_Manager_Post_Types::restrict_content_html
	 */
	public function test_brackets_in_a_link_address_are_percent_encoded() {
		$restricted = WP_Job_Manager_Post_Types::restrict_content_html( '<a href="https://example.com/?a[]=1">x [1]</a>' );

		$this->assertSame( '<a href="https://example.com/?a%5B%5D=1">x &#091;1&#093;</a>', $restricted );
	}

	public function data_urls_in_descriptions() {
		return [
			'address on its own line' => [ "Line\nhttps://video.example.org/watch/123\nLine" ],
			'embed shortcode'         => [ '[embed]https://video.example.org/watch/123[/embed]' ],
		];
	}

	/**
	 * @dataProvider data_urls_in_descriptions
	 * @covers WP_Job_Manager_Post_Types::limit_embed_discovery
	 */
	public function test_embed_is_not_looked_up_at_an_address_in_untrusted_description( $content ) {
		$requests = $this->record_http_requests();
		$job_id   = $this->create_listing( 'subscriber', $content );

		$single = $this->render_single_listing( $job_id );

		$this->assertSame( [], $requests->getArrayCopy(), 'The listing author chooses this address, so the site must not fetch it.' );
		$this->assertStringNotContainsString( '<iframe', $single );
	}

	/**
	 * @dataProvider data_urls_in_descriptions
	 * @covers WP_Job_Manager_Post_Types::limit_embed_discovery
	 */
	public function test_embed_is_looked_up_at_an_address_in_trusted_description( $content ) {
		$requests = $this->record_http_requests();
		$job_id   = $this->create_listing( 'administrator', $content );

		$this->render_single_listing( $job_id );

		$this->assertContains( 'https://video.example.org/watch/123', $requests->getArrayCopy() );
	}

	public function data_media_addresses() {
		return [
			'video file' => [ "Line\nhttp://&lt;b/class=wpjm-probe&gt;?x#.mp4\nLine" ],
			'audio file' => [ "Line\nhttps://example.com/a.mp3\nLine" ],
		];
	}

	/**
	 * WordPress turns the address of a media file into a player. For an untrusted listing it
	 * must be a link on every path, including shortcode output that is processed again.
	 *
	 * @dataProvider data_media_addresses
	 * @covers WP_Job_Manager_Post_Types::limit_listing_media_embeds
	 */
	public function test_no_player_is_built_from_a_media_address_in_untrusted_description( $content ) {
		$job_id = $this->create_listing( 'subscriber', $content );

		$single    = $this->render_single_listing( $job_id );
		$shortcode = do_shortcode( '[job id="' . $job_id . '"]' );

		foreach ( [ $single, do_shortcode( $single ), $shortcode, do_shortcode( $shortcode ) ] as $output ) {
			$this->assertDoesNotMatchRegularExpression( '#<(video|audio|source)\b|\[(video|audio)\b#i', $output );
		}
	}

	/**
	 * @covers WP_Job_Manager_Post_Types::limit_listing_media_embeds
	 */
	public function test_player_is_built_from_a_media_address_in_trusted_description() {
		$job_id = $this->create_listing( 'administrator', "Line\nhttps://example.com/a.mp4\nLine" );

		$this->assertStringContainsString( '<video', $this->render_single_listing( $job_id ) );
	}

	/**
	 * A description can be requested for a listing other than the current post. Trust follows
	 * the listing the description belongs to, in both directions.
	 *
	 * @covers WP_Job_Manager_Post_Types::limit_embed_discovery
	 * @covers WP_Job_Manager_Post_Types::limit_listing_media_embeds
	 */
	public function test_embeds_follow_the_listing_being_processed_not_the_current_post() {
		$content    = "Line\nhttps://video.example.org/watch/123\nLine\nhttps://example.com/a.mp4\nLine";
		$subscriber = $this->create_listing( 'subscriber', $content );
		$admin      = $this->create_listing( 'administrator', $content );

		$requests        = $this->record_http_requests();
		$GLOBALS['post'] = get_post( $admin ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$untrusted       = wpjm_get_the_job_description( $subscriber );

		$this->assertSame( [], $requests->getArrayCopy() );
		$this->assertStringNotContainsString( '[video', $untrusted );

		$GLOBALS['post'] = get_post( $subscriber ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$trusted         = wpjm_get_the_job_description( $admin );
		unset( $GLOBALS['post'] );

		$this->assertContains( 'https://video.example.org/watch/123', $requests->getArrayCopy() );
		$this->assertStringContainsString( '[video', $trusted );
	}

	/**
	 * Embeds are cached with the listing, so one that was discovered before must not be printed
	 * from the cache either.
	 *
	 * @covers WP_Job_Manager_Post_Types::limit_listing_embeds
	 */
	public function test_cached_embed_from_unknown_provider_is_replaced_with_a_link() {
		$frame      = '<iframe class="wp-embedded-content" sandbox="allow-scripts" src="https://video.example.org/embed/123"></iframe>';
		$subscriber = $this->create_listing( 'subscriber', 'x' );
		$guest      = $this->create_listing( false, 'x' );
		$admin      = $this->create_listing( 'administrator', 'x' );

		foreach ( [ $subscriber, $guest ] as $job_id ) {
			$html = apply_filters( 'embed_oembed_html', $frame, 'https://video.example.org/watch/123', [], $job_id );

			$this->assertStringNotContainsString( '<iframe', $html );
			$this->assertStringContainsString( 'https://video.example.org/watch/123', $html );
		}

		$this->assertSame( $frame, apply_filters( 'embed_oembed_html', $frame, 'https://video.example.org/watch/123', [], $admin ) );
	}

	/**
	 * @covers WP_Job_Manager_Post_Types::limit_listing_embeds
	 */
	public function test_embed_from_known_provider_is_kept_for_untrusted_listing() {
		$frame  = '<iframe src="https://www.youtube.com/embed/abc?feature=oembed"></iframe>';
		$job_id = $this->create_listing( 'subscriber', 'x' );

		$this->assertSame( $frame, apply_filters( 'embed_oembed_html', $frame, 'https://www.youtube.com/watch?v=abc', [], $job_id ) );
	}

	/**
	 * @covers WP_Job_Manager_Post_Types::limit_listing_embeds
	 */
	public function test_embeds_in_other_posts_are_left_alone() {
		$frame   = '<iframe class="wp-embedded-content" src="https://video.example.org/embed/123"></iframe>';
		$post_id = $this->factory->post->create( [ 'post_author' => $this->factory->user->create( [ 'role' => 'subscriber' ] ) ] );

		$this->assertSame( $frame, apply_filters( 'embed_oembed_html', $frame, 'https://video.example.org/watch/123', [], $post_id ) );
	}

	/**
	 * Finds attribute values that hold a bracket once decoded.
	 *
	 * @param string $html HTML.
	 * @return string[]
	 */
	private function find_brackets_in_attributes( $html ) {
		$found = [];
		$tags  = new WP_HTML_Tag_Processor( $html );

		while ( $tags->next_tag() ) {
			foreach ( (array) $tags->get_attribute_names_with_prefix( '' ) as $name ) {
				$value = $tags->get_attribute( $name );
				if ( is_string( $value ) && preg_match( '/[\[\]]/', $value ) ) {
					$found[] = $name . ': ' . $value;
				}
			}
		}

		return $found;
	}

	private function record_http_requests() {
		$requests = new ArrayObject();

		add_filter(
			'pre_http_request',
			function ( $response, $args, $url ) use ( $requests ) {
				$requests[] = $url;

				return new WP_Error( 'wpjm_test', 'No requests in tests.' );
			},
			10,
			3
		);

		return $requests;
	}

	private function create_listing( $role, $content ) {
		global $wpdb;

		$job_id = $this->factory->job_listing->create(
			[
				'post_author' => $role ? $this->factory->user->create( [ 'role' => $role ] ) : 0,
			]
		);

		// Written directly, as content saved by an earlier version would be.
		$wpdb->update( $wpdb->posts, [ 'post_content' => $content ], [ 'ID' => $job_id ] );
		clean_post_cache( $job_id );

		return $job_id;
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
