<?php
/**
 * Regression tests for media built from a listing's URL fields.
 *
 * The company video player is built by the plugin, but from a URL the listing's author
 * entered. Players read that URL back from the page, so it has to be a plain URL however it
 * is decoded, on every path that prints the player and for every listing.
 *
 * @package wp-job-manager/tests
 */

class Tests_Listing_Media_Url extends WPJM_BaseTest {

	/**
	 * A URL whose attribute value decodes to markup.
	 */
	const ENCODED_MARKUP_URL = 'http://&lt;b/class=wpjm-probe&gt;?x#.mp4';

	public function tearDown(): void {
		remove_all_filters( 'the_company_video_embed' );
		remove_all_filters( 'the_company_video' );
		remove_all_filters( 'pre_http_request' );
		parent::tearDown();
	}

	public function data_usable_urls() {
		return [
			'video file'              => [ 'https://example.com/media/company.mp4', 'https://example.com/media/company.mp4' ],
			'query string'            => [ 'https://www.youtube.com/watch?v=abc123&t=10', 'https://www.youtube.com/watch?v=abc123&t=10' ],
			'saved with encoded amp'  => [ 'https://example.com/v.mp4?a=1&amp;b=2', 'https://example.com/v.mp4?a=1&b=2' ],
			'no scheme'               => [ 'example.com/company.mp4', 'http://example.com/company.mp4' ],
			'site relative'           => [ '/wp-content/uploads/company.mp4', '/wp-content/uploads/company.mp4' ],
			'surrounding whitespace'  => [ '  https://example.com/company.mp4 ', 'https://example.com/company.mp4' ],
			'param named like entity' => [ 'https://example.com/v.mp4?lt=1&gt=2', 'https://example.com/v.mp4?lt=1&gt=2' ],
			'brackets in query'       => [ 'https://example.com/v.mp4?a[]=1', 'https://example.com/v.mp4?a%5B%5D=1' ],
			'shortcode in the path'   => [ 'https://example.com/[gallery]/v.mp4', 'https://example.com/%5Bgallery%5D/v.mp4' ],
			'space in the path'       => [ 'https://example.com/my video.mp4', 'https://example.com/my%20video.mp4' ],
			'percent encoded'         => [ 'https://example.com/a%3Cb%3E.mp4', 'https://example.com/a%3Cb%3E.mp4' ],
			'port and fragment'       => [ 'https://example.com:8443/v.mp4#t=10', 'https://example.com:8443/v.mp4#t=10' ],
			'non-ASCII path'          => [ 'https://example.com/vidéo.mp4', 'https://example.com/vidéo.mp4' ],
			'angle brackets removed'  => [ 'https://example.com/<b>.mp4', 'https://example.com/b.mp4' ],
			'quote removed'           => [ 'https://example.com/a"b.mp4', 'https://example.com/ab.mp4' ],
			'backtick removed'        => [ 'https://example.com/a`b.mp4', 'https://example.com/ab.mp4' ],
			'ampersand before a name' => [ 'https://example.com/v.mp4?a=1&copy=2&reg=3', 'https://example.com/v.mp4?a=1&copy=2&reg=3' ],
		];
	}

	/**
	 * @dataProvider data_usable_urls
	 * @covers WP_Job_Manager_Post_Types::sanitize_media_url
	 */
	public function test_usable_url_is_kept( $url, $expected ) {
		$this->assertSame( $expected, WP_Job_Manager_Post_Types::sanitize_media_url( $url ) );
	}

	public function data_unusable_urls() {
		return [
			'named references'          => [ self::ENCODED_MARKUP_URL ],
			'decimal references'        => [ 'http://&#60;b&#62;?x#.mp4' ],
			'hex references'            => [ 'http://&#x3c;b&#x3E;?x#.mp4' ],
			'ampersand encoded twice'   => [ 'https://example.com/v.mp4?a=1&amp;amp;b=2' ],
			'encoded twice'             => [ 'http://&amp;lt;b&amp;gt;?x#.mp4' ],
			'encoded three times'       => [ 'http://&amp;amp;lt;b&amp;amp;gt;?x#.mp4' ],
			'quote reference'           => [ 'https://example.com/a&quot;b.mp4' ],
			'apostrophe reference'      => [ 'https://example.com/a&#039;b.mp4' ],
			'apostrophe'                => [ "https://example.com/a'b.mp4" ],
			'reference joined by clean' => [ 'http://&l{t;b&g{t;?x#.mp4' ],
			'other named reference'     => [ 'https://example.com/a&nbsp;b.mp4' ],
			'bracket reference'         => [ 'https://example.com/&#91;gallery&#93;.mp4' ],
			'bracket reference, named'  => [ 'https://example.com/&lbrack;gallery&rbrack;.mp4' ],
			'only spaces'               => [ '   ' ],
			'script scheme'             => [ 'javascript:alert(1)//.mp4' ],
			'data scheme'               => [ 'data:text/html,x.mp4' ],
			'empty'                     => [ '' ],
			'not a string'              => [ [ 'https://example.com/company.mp4' ] ],
			'null'                      => [ null ],
		];
	}

	/**
	 * A URL that was kept is not changed by sanitizing it again, by decoding it as HTML, or by
	 * the escaping WordPress applies when it prints it, apart from the ampersand.
	 *
	 * @dataProvider data_usable_urls
	 * @covers WP_Job_Manager_Post_Types::sanitize_media_url
	 */
	public function test_kept_url_is_stable( $url, $expected ) {
		$this->assertSame( $expected, WP_Job_Manager_Post_Types::sanitize_media_url( $expected ) );
		$this->assertSame( $expected, html_entity_decode( $expected, ENT_QUOTES | ENT_HTML5 ) );
		$this->assertSame( $expected, html_entity_decode( esc_url( $expected ), ENT_QUOTES | ENT_HTML5 ) );
		$this->assertSame( $expected, html_entity_decode( esc_attr( $expected ), ENT_QUOTES | ENT_HTML5 ) );
	}

	/**
	 * An address with brackets in the host is not cleaned by WordPress, and the player prints
	 * the URL as text too.
	 *
	 * @covers WP_Job_Manager_Post_Types::sanitize_media_url
	 */
	public function test_no_bracket_is_left_in_a_url() {
		foreach ( [ 'http://[cafe::1]/v.mp4', 'http://[cafe::1]:8080/[gallery].mp4', 'https://example.com/[a]/[b].mp4?c[]=[d]#[e]' ] as $url ) {
			$this->assertDoesNotMatchRegularExpression( '/[\[\]]/', WP_Job_Manager_Post_Types::sanitize_media_url( $url ), $url );
		}
	}

	/**
	 * The rule the sanitizer exists for, tried on a few thousand generated inputs: whatever it
	 * returns is unchanged by sanitizing again, by decoding, and by WordPress's own player
	 * markup once a browser has decoded the attribute. The inputs are generated from a fixed
	 * seed, so a failure can be repeated.
	 *
	 * @covers WP_Job_Manager_Post_Types::sanitize_media_url
	 * @covers WP_Job_Manager_Post_Types::is_inert_media_markup
	 */
	public function test_generated_urls_are_rejected_or_come_out_of_the_player_unchanged() {
		$pieces = [ 'http://', 'https://', 'example.com', '/', '?', '#', '.mp4', 'a', 'b=1', '=', '%27', '%60', '%3E', '%5B', '&', ';', '&amp;', '&lt;', '&gt;', '&quot;', '&#39;', '&#039;', '&#60;', '&#x3c;', '&#60', '&lt', '&#91;', '&lbrack;', '&#96;', '&l', 't;', '&#', '6', '0', 'x3c', '<', '>', '"', "'", '`', '[', ']', '{', '}', '^', '|', '\\', ' ', "\t", "\n", '%', '%3C', '%22', '%00', 'javascript:', 'data:', '//', ':', '@', 'é', '&nbsp;', '&copy', 'onload=', 'iframe' ];
		$kept   = 0;

		mt_srand( 20261113 );

		for ( $i = 0; $i < 4000; $i++ ) {
			$url = 0 === $i % 2 ? 'https://example.com/' : '';
			for ( $j = mt_rand( 1, 9 ); $j > 0; $j-- ) {
				$url .= $pieces[ mt_rand( 0, count( $pieces ) - 1 ) ];
			}

			$clean = WP_Job_Manager_Post_Types::sanitize_media_url( $url );
			if ( '' === $clean ) {
				continue;
			}
			++$kept;

			$this->assertSame( $clean, WP_Job_Manager_Post_Types::sanitize_media_url( $clean ), "Sanitizing again changed: $url" );
			$this->assertSame( $clean, html_entity_decode( $clean, ENT_QUOTES | ENT_HTML5 ), "Decoding changed: $url" );
			$this->assertDoesNotMatchRegularExpression( '/[<>"\'`\[\]\s]/', $clean, "Unsafe character kept: $url" );

			// WordPress rewrites the query string when it builds the player, so the markup is
			// read back the way a browser reads it.
			$markup = wp_video_shortcode( [ 'src' => $clean . '#.mp4' ] );
			$tags   = new WP_HTML_Tag_Processor( $markup );
			$this->assertTrue( $tags->next_tag( 'source' ), "No source element: $url" );
			$source = html_entity_decode( (string) $tags->get_attribute( 'src' ), ENT_QUOTES | ENT_HTML5 );

			$this->assertDoesNotMatchRegularExpression( '/[<>"`]/', $source, "The player source has markup: $url" );

			// The markup check must refuse whatever the sanitizer could not rule out. Before
			// WordPress 6.6 it may also refuse markup that is fine.
			if ( preg_match( '/[<>"\'`]/', $source ) ) {
				$this->assertFalse( WP_Job_Manager_Post_Types::is_inert_media_markup( $markup ), "Unsafe player markup was accepted: $url" );
			} elseif ( class_exists( 'WP_HTML_Decoder' ) ) {
				$this->assertTrue( WP_Job_Manager_Post_Types::is_inert_media_markup( $markup ), "Player markup is not inert: $url" );
			}
		}

		mt_srand();

		$this->assertGreaterThan( 200, $kept, 'The generator should produce a useful number of URLs that are kept.' );
	}

	/**
	 * @dataProvider data_unusable_urls
	 * @covers WP_Job_Manager_Post_Types::sanitize_media_url
	 */
	public function test_unusable_url_is_rejected( $url ) {
		$this->assertSame( '', WP_Job_Manager_Post_Types::sanitize_media_url( $url ) );
	}

	/**
	 * Whatever the sanitizer returns must come out of WordPress's player markup unchanged
	 * when the browser decodes it.
	 *
	 * @dataProvider data_usable_urls
	 * @covers WP_Job_Manager_Post_Types::sanitize_media_url
	 */
	public function test_kept_url_is_inert_in_player_markup( $url ) {
		$clean = WP_Job_Manager_Post_Types::sanitize_media_url( $url );

		$this->assertTrue( WP_Job_Manager_Post_Types::is_inert_media_markup( wp_video_shortcode( [ 'src' => $clean . '#.mp4' ] ) ) );
	}

	public function data_markup() {
		return [
			'player'                          => [ true, '<video controls><source type="video/mp4" src="https://example.com/a.mp4?_=1&#038;b=2" /><a href="https://example.com/a.mp4">https://example.com/a.mp4</a></video>' ],
			'provider iframe'                 => [ true, '<iframe title="A &lt;great&gt; &quot;video&quot;" width="500" src="https://www.youtube.com/embed/abc?feature=oembed" allowfullscreen></iframe>' ],
			'shortcode text'                  => [ true, '[flowplayer src="https://example.com/a.mp4"]' ],
			'markup as text'                  => [ true, '<p>1 &lt; 2 and "quoted"</p>' ],
			'player settings and title'       => [ true, '<video data-setup=\'{"fluid": true}\' title="Bob&#039;s video" src="a.mp4"></video>' ],
			'title of a media element'        => [ false, '<video title="&lt;b&gt;" src="a.mp4"></video>' ],
			'source decodes to markup'        => [ false, '<video><source src="http://&lt;b&gt;?x#.mp4" /></video>' ],
			'source decodes to a quote'       => [ false, '<video><source src="http://example.com/&quot;onfocus=x#.mp4" /></video>' ],
			'source decodes to an apostrophe' => [ false, '<video><source src="http://example.com/&#039;onfocus=x#.mp4" /></video>' ],
			'numeric reference'               => [ false, '<video><source src="http://&#60;b&#62;#.mp4" /></video>' ],
			'reference without a semicolon'   => [ false, '<video><source src="http://&#60b#.mp4" /></video>' ],
			'reference left after decoding'   => [ false, '<video><source src="http://&amp;lt;b#.mp4" /></video>' ],
			'track label'                     => [ false, '<video src="a.mp4"><track label="&lt;b&gt;" src="a.vtt" /></video>' ],
			'video poster'                    => [ false, '<video poster="http://&lt;b&gt;" src="a.mp4"></video>' ],
			'link in fallback'                => [ false, '<video src="a.mp4"><a href="http://&lt;b&gt;">x</a></video>' ],
			'iframe source'                   => [ false, '<iframe src="http://&lt;b&gt;"></iframe>' ],
			'single quoted attribute'         => [ false, "<audio src='http://&lt;b&gt;'></audio>" ],
			'reference encoded twice in title' => [ false, '<video title="&amp;lt;b&amp;gt;" src="a.mp4"></video>' ],
			'upper case tag and attribute'    => [ false, '<VIDEO><SOURCE SRC="http://&lt;b&gt;#.mp4"></VIDEO>' ],
			'unquoted attribute'              => [ false, '<video><source src=http://&lt;b&gt;#.mp4></video>' ],
			'second of two sources'           => [ false, '<video><source src="a.mp4"><source src="http://&lt;b&gt;#.mp4"></video>' ],
			'backtick in a source'            => [ false, '<video><source src="http://example.com/&#96;x#.mp4" /></video>' ],
			'image source set'                => [ false, '<img srcset="http://&lt;b&gt; 2x" src="a.png">' ],
			'object data'                     => [ false, '<object data="http://&lt;b&gt;"></object>' ],
			'query string named like entity'  => [ true, '<video><source src="https://example.com/a.mp4?lt=1&#038;gt=2" /></video>' ],
			'quotes in a paragraph attribute' => [ true, '<p title="Say &quot;hi&quot; &lt;there&gt;">x</p>' ],
			'empty'                           => [ true, '' ],
			'not a string'                    => [ false, null ],
		];
	}

	/**
	 * @dataProvider data_markup
	 * @covers WP_Job_Manager_Post_Types::is_inert_media_markup
	 */
	public function test_media_markup_check( $expected, $html ) {
		$this->assertSame( $expected, WP_Job_Manager_Post_Types::is_inert_media_markup( $html ) );
	}

	public function data_authors() {
		return [
			'guest'         => [ false ],
			'subscriber'    => [ 'subscriber' ],
			'employer'      => [ 'employer' ],
			'administrator' => [ 'administrator' ],
		];
	}

	/**
	 * @dataProvider data_authors
	 * @covers ::the_company_video
	 * @covers ::get_the_company_video
	 */
	public function test_no_player_is_built_from_unusable_url_on_listing_page( $role ) {
		$job_id = $this->create_listing( $role, self::ENCODED_MARKUP_URL );

		$output = $this->render_single_listing( $job_id );

		$this->assertSame( '', get_the_company_video( $job_id ) );
		$this->assertNoMediaFromListingUrl( $output );
	}

	/**
	 * Themes and shortcodes print the player outside the filtered listing content, where
	 * nothing else looks at the markup.
	 *
	 * @dataProvider data_authors
	 * @covers ::the_company_video
	 */
	public function test_no_player_is_built_from_unusable_url_outside_listing_content( $role ) {
		$job_id = $this->create_listing( $role, self::ENCODED_MARKUP_URL );

		ob_start();
		the_company_video( $job_id );
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * @dataProvider data_authors
	 * @covers ::the_company_video
	 */
	public function test_player_is_built_from_usable_url( $role ) {
		$job_id = $this->create_listing( $role, 'https://example.com/media/company.mp4' );

		$output = $this->render_single_listing( $job_id );

		$this->assertStringContainsString( '<div class="company_video">', $output );
		$this->assertMatchesRegularExpression( '#<source[^>]+src="https://example\.com/media/company\.mp4#', $output );
	}

	/**
	 * @covers ::get_the_company_video
	 */
	public function test_filtered_video_url_is_checked() {
		$job_id = $this->create_listing( 'administrator', 'https://example.com/media/company.mp4' );
		add_filter( 'the_company_video', [ $this, 'return_encoded_markup_url' ] );

		ob_start();
		the_company_video( $job_id );
		$output = ob_get_clean();

		$this->assertSame( '', get_the_company_video( $job_id ) );
		$this->assertSame( '', $output );
	}

	/**
	 * @dataProvider data_authors
	 * @covers ::the_company_video
	 * @covers WP_Job_Manager_Post_Types::protect_listing_media_output
	 */
	public function test_filtered_embed_markup_is_checked( $role ) {
		$job_id = $this->create_listing( $role, 'https://example.com/media/company.mp4' );
		add_filter( 'the_company_video_embed', [ $this, 'return_player_with_encoded_markup' ] );

		ob_start();
		the_company_video( $job_id );
		$direct = ob_get_clean();
		$single = $this->render_single_listing( $job_id );

		$this->assertSame( '', $direct );
		$this->assertNoMediaFromListingUrl( $single );
	}

	/**
	 * @covers WP_Job_Manager_Post_Types::protect_listing_media_output
	 */
	public function test_markup_that_is_not_inert_is_never_kept() {
		$job_id = $this->create_listing( 'subscriber', 'https://example.com/media/company.mp4' );

		$this->assertSame( '', WP_Job_Manager_Post_Types::protect_listing_media_output( $this->return_player_with_encoded_markup(), $job_id ) );
	}

	/**
	 * @covers WP_Job_Manager_Post_Types::sanitize_meta_field_media_url
	 */
	public function test_unusable_url_is_not_saved() {
		$job_id = $this->create_listing( 'subscriber', 'https://example.com/media/company.mp4' );

		update_post_meta( $job_id, '_company_video', self::ENCODED_MARKUP_URL );

		$this->assertSame( '', get_post_meta( $job_id, '_company_video', true ) );
	}

	/**
	 * @covers WP_Job_Manager_Post_Types::sanitize_meta_field_media_url
	 */
	public function test_usable_url_is_saved() {
		$job_id = $this->create_listing( 'subscriber', '' );

		update_post_meta( $job_id, '_company_video', 'https://www.youtube.com/watch?v=abc123&t=10' );

		$this->assertSame( 'https://www.youtube.com/watch?v=abc123&t=10', get_post_meta( $job_id, '_company_video', true ) );
	}

	/**
	 * @covers ::the_company_video
	 */
	public function test_embed_is_not_looked_up_at_the_url_of_untrusted_listing() {
		$requests = $this->record_http_requests();
		$job_id   = $this->create_listing( 'subscriber', 'https://video.example.org/watch/123' );

		ob_start();
		the_company_video( $job_id );
		$output = ob_get_clean();

		$this->assertSame( [], $requests->getArrayCopy(), 'The listing author chooses this URL, so the site must not fetch it.' );
		$this->assertSame( '', $output );
	}

	/**
	 * @covers ::the_company_video
	 */
	public function test_embed_is_looked_up_at_the_url_of_trusted_listing() {
		$requests = $this->record_http_requests();
		$job_id   = $this->create_listing( 'administrator', 'https://video.example.org/watch/123' );

		ob_start();
		the_company_video( $job_id );
		ob_get_clean();

		$this->assertContains( 'https://video.example.org/watch/123', $requests->getArrayCopy() );
	}

	public function return_encoded_markup_url() {
		return self::ENCODED_MARKUP_URL;
	}

	public function return_player_with_encoded_markup() {
		return '<video controls><source type="video/mp4" src="http://&lt;b/class=wpjm-probe&gt;?x#.mp4" /></video>';
	}

	/**
	 * Fails when any element's attribute decodes to the markup in ENCODED_MARKUP_URL, or the
	 * player was printed at all.
	 */
	private function assertNoMediaFromListingUrl( $output ) {
		$this->assertStringNotContainsString( 'company_video', $output );
		$this->assertStringNotContainsString( '<video', $output );
		$this->assertStringNotContainsString( '<source', $output );
		$this->assertStringNotContainsString( 'wpjm-probe', $output );
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

	private function create_listing( $role, $video_url ) {
		$job_id = $this->factory->job_listing->create(
			[
				'post_author' => $role ? $this->factory->user->create( [ 'role' => $role ] ) : 0,
				'meta_input'  => [ '_company_name' => 'Acme' ],
			]
		);

		// Written directly, as a value saved by an earlier version would be.
		global $wpdb;
		delete_post_meta( $job_id, '_company_video' );
		$wpdb->insert(
			$wpdb->postmeta,
			[
				'post_id'    => $job_id,
				'meta_key'   => '_company_video', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $video_url, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);
		wp_cache_delete( $job_id, 'post_meta' );

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
