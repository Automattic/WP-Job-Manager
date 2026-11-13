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
			'unquoted'                   => [ '<a href=' . $sc . '>x</a>' ],
			'bare in the tag'            => [ '<a ' . $sc . ' href="#">x</a>' ],
			'as an attribute name'       => [ '<a ' . $sc . '="1" href="#">x</a>' ],
			'upper case'                 => [ '<A TITLE="' . $sc . '" HREF="#">x</A>' ],
			'two attributes'             => [ '<a title="' . $sc . '" href="https://example.com/' . $sc . '">x</a>' ],
			'with an angle bracket'      => [ '<a title="a > b ' . $sc . '" href="#">x</a>' ],
			'with an encoded bracket'    => [ '<a title="a &gt; b ' . $sc . '" href="#">x</a>' ],
			'self-closing tag'           => [ '<img src="x.png" alt="' . $sc . '" />' ],
			'in a comment'               => [ '<!-- ' . $sc . ' --><p>x</p>' ],
			'tag left open'              => [ '<a title="' . $sc . '" href="#">x' ],
			'attribute left open'        => [ '<a href="#" title="' . $sc ],
			'encoded twice'              => [ '<a title="&amp;#91;' . self::SHORTCODE . '&amp;#93;" href="#">x</a>' ],
			'next to text'               => [ $sc . '<a title="' . $sc . '" href="#">' . $sc . '</a>' . $sc ],
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

	public function data_exact_output() {
		return [
			'link address and text'     => [ '<a href="https://example.com/?a[]=1">x [1]</a>', '<a href="https://example.com/?a%5B%5D=1">x &#091;1&#093;</a>' ],
			'other characters are kept' => [ '<a title="Tom &amp; Jerry [1] &lt;b&gt;" href="#">x</a>', '<a title="Tom &amp; Jerry %5B1%5D &lt;b&gt;" href="#">x</a>' ],
			'attributes without one'    => [ '<a title="plain" href="https://example.com/a?b=1&amp;c=2">x</a>', '<a title="plain" href="https://example.com/a?b=1&amp;c=2">x</a>' ],
			'comment'                   => [ '<!-- [a] --><p>x</p>', '<!-- %5Ba%5D --><p>x</p>' ],
			'nothing to do'             => [ '<p>Plain <strong>text</strong>.</p>', '<p>Plain <strong>text</strong>.</p>' ],
			'text only'                 => [ 'Salary [a] to [b]', 'Salary &#091;a&#093; to &#091;b&#093;' ],
			'non-ASCII'                 => [ '<a title="Ünïcode [é] 日本" href="#">日本 [語]</a>', '<a title="Ünïcode %5Bé%5D 日本" href="#">日本 &#091;語&#093;</a>' ],
		];
	}

	/**
	 * The restriction changes brackets and nothing else.
	 *
	 * @dataProvider data_exact_output
	 * @covers WP_Job_Manager_Post_Types::restrict_content_html
	 */
	public function test_restricted_content_is_otherwise_unchanged( $content, $expected ) {
		$this->assertSame( $expected, WP_Job_Manager_Post_Types::restrict_content_html( $content ) );
	}

	/**
	 * The rule the restriction exists for, tried on a few thousand generated descriptions:
	 * after the HTML filter has rewritten the result, no attribute holds a bracket, and
	 * expanding shortcodes twice runs nothing. Generated from a fixed seed.
	 *
	 * @covers WP_Job_Manager_Post_Types::restrict_content_html
	 */
	public function test_generated_descriptions_never_leave_a_live_shortcode() {
		$name   = self::SHORTCODE;
		$pieces = [ '[', ']', "[$name]", "[/$name]", "[[$name]]", "[$name /]", "[$name a=\"b\"]", '&#91;', '&#93;', '&#091;', '&#093;', '&#x5b;', '&#x5D;', '&lbrack;', '&rbrack;', '&lsqb;', '&rsqb;', '&#91', '&#93', '&amp;', '&amp;#91;', '&lt;', '&gt;', '&quot;', $name, '<a href="', '<a title="', "<a title='", '<a href=', '<img alt="', '<p class="', '<p ', '<div data-x="', '<span ', '">', "'>", '>', '" href="#">', '</a>', '</p>', '<!-- ', ' -->', '<br />', '<', '"', "'", '=', ' ', "\n", 'x', 'https://example.com/', '?a=1&b=2', '<video src="', '<iframe src="', '<script>', '</script>', '<![CDATA[', ']]>' ];

		mt_srand( 20261113 );

		for ( $i = 0; $i < 3000; $i++ ) {
			$content = '';
			for ( $j = mt_rand( 2, 14 ); $j > 0; $j-- ) {
				$content .= $pieces[ mt_rand( 0, count( $pieces ) - 1 ) ];
			}

			$restricted = WP_Job_Manager_Post_Types::restrict_content_html( $content );
			$filtered   = wp_kses_post( $restricted );

			foreach ( wp_html_split( $restricted ) as $part ) {
				if ( '' !== $part && '<' === $part[0] ) {
					$this->assertDoesNotMatchRegularExpression( '/[\[\]]/', html_entity_decode( $part, ENT_QUOTES | ENT_HTML5 ), "Bracket left in a tag: $content" );
				}
			}
			$this->assertSame( [], $this->find_brackets_in_attributes( $restricted ), "Bracket left in an attribute: $content" );
			$this->assertSame( [], $this->find_brackets_in_attributes( $filtered ), "Bracket in an attribute after the HTML filter: $content" );

			$this->ran = 0;
			do_shortcode( do_shortcode( $restricted ) );
			do_shortcode( do_shortcode( $filtered ) );
			do_shortcode( do_shortcode( wp_kses_post( wpautop( wptexturize( $restricted ) ) ) ) );
			$this->assertSame( 0, $this->ran, "A shortcode ran: $content" );
		}

		mt_srand();
	}

	/**
	 * Every way PHP knows to write a bracket as a character reference is replaced inside a
	 * tag. The named references are read from PHP's own table, so nothing here lists them.
	 *
	 * @covers WP_Job_Manager_Post_Types::restrict_content_html
	 */
	public function test_every_reference_to_a_bracket_is_replaced_in_a_tag() {
		$references = [];
		foreach ( get_html_translation_table( HTML_ENTITIES, ENT_QUOTES | ENT_HTML5 ) as $character => $reference ) {
			if ( '[' === $character || ']' === $character ) {
				$references[] = $reference;
			}
		}
		$this->assertGreaterThanOrEqual( 2, count( $references ), 'PHP should know named references for both brackets.' );

		foreach ( [ 91, 93 ] as $code ) {
			foreach ( [ '&#%d;', '&#0%d;', '&#000000%d;', '&#%d' ] as $format ) {
				$references[] = sprintf( $format, $code );
			}
			foreach ( [ '&#x%x;', '&#X%X;', '&#x00%x;', '&#x%x' ] as $format ) {
				$references[] = sprintf( $format, $code );
			}
		}

		foreach ( $references as $reference ) {
			foreach ( [ '<a title="%s" href="#">x</a>', "<a title='%s' href='#'>x</a>", '<a href="https://example.com/%s">x</a>', '<!-- " href="#"><a title="%s">x', '<p class="a %s b">x</p>' ] as $markup ) {
				$content    = sprintf( $markup, $reference . 'x ' . $reference );
				$restricted = WP_Job_Manager_Post_Types::restrict_content_html( $content );

				foreach ( wp_html_split( $restricted ) as $part ) {
					if ( '' === $part || '<' !== $part[0] ) {
						continue;
					}
					$this->assertDoesNotMatchRegularExpression( '/[\[\]]/', html_entity_decode( $part, ENT_QUOTES | ENT_HTML5 ), "Reference left in a tag: $content" );
				}
			}
		}
	}

	public function data_tags_without_the_html_filter() {
		return [
			'reference with no semicolon' => [ '<a title="&#91x&#93" href="#">', '<a title="%5Bx%5D" href="#">' ],
			'hex with no semicolon'       => [ '<a title="&#x5bx&#x5D ">', '<a title="%5Bx%5D ">' ],
			'comment left open'           => [ '<!-- " href="#"><a title="&#93;&lsqb;">[x]', '<!-- " href="#"><a title="%5D%5B">%5Bx%5D' ],
			'outside an attribute'        => [ '<a [x] &#91;y&#93; href=[z]>[t]</a>', '<a %5Bx%5D %5By%5D href=%5Bz%5D>[t]</a>' ],
			'other references are kept'   => [ '<a title="&amp; &lt; &#39; &#60; &copy; &#9731;" href="?a=1&b=2&lbrack=3">', '<a title="&amp; &lt; &#39; &#60; &copy; &#9731;" href="?a=1&b=2%5B=3">' ],
			'text is not touched'         => [ 'a [b] &#91;c&#93; <i>[d]</i>', 'a [b] &#91;c&#93; <i>[d]</i>' ],
		];
	}

	/**
	 * The replacement inside tags, on its own. In use it runs after the HTML filter, which
	 * has already normalised most of these; it must not depend on that.
	 *
	 * @dataProvider data_tags_without_the_html_filter
	 * @covers WP_Job_Manager_Post_Types::encode_brackets_in_tags
	 */
	public function test_brackets_in_tags_are_replaced_without_the_html_filter( $content, $expected ) {
		$method = new ReflectionMethod( WP_Job_Manager_Post_Types::class, 'encode_brackets_in_tags' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$this->assertSame( $expected, $method->invoke( null, $content ) );
	}

	/**
	 * An attribute that is rewritten keeps its value. How WordPress writes an apostrophe back
	 * depends on its version, so the value is compared as the browser reads it.
	 *
	 * @covers WP_Job_Manager_Post_Types::restrict_content_html
	 */
	public function test_rewritten_attribute_keeps_its_value() {
		$restricted = WP_Job_Manager_Post_Types::restrict_content_html( '<a title="Tom &amp; Jerry&#039;s &quot;best&quot; [1] &lt;b&gt;" href="#">x</a>' );

		$tags = new WP_HTML_Tag_Processor( $restricted );
		$this->assertTrue( $tags->next_tag() );
		$this->assertSame( 'Tom & Jerry\'s "best" %5B1%5D <b>', html_entity_decode( $tags->get_attribute( 'title' ), ENT_QUOTES | ENT_HTML5 ) );
		$this->assertSame( '#', $tags->get_attribute( 'href' ) );
		$this->assertFalse( $tags->next_tag(), 'The value must stay inside its attribute.' );
		$this->assertStringEndsWith( '>x</a>', $restricted );
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
