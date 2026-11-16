<?php
/**
 * The single listing template is rendered while post content is filtered, and prints listing fields, forms and
 * request data. It is kept apart from the other content filters: they see a placeholder, and the rendered template
 * takes its place after the last of them. Only the description of a trusted listing is searched for shortcodes.
 *
 * @package wp-job-manager/tests
 */

class Tests_Single_Listing_Content_Processing extends WPJM_BaseTest {

	const SHORTCODE = 'wpjm_test_single_sc';
	const EXPANDED  = 'SINGLE_SC_EXPANDED_SENTINEL';

	/**
	 * How many times the test shortcode ran.
	 *
	 * @var int
	 */
	public static $runs = 0;

	public function setUp(): void {
		parent::setUp();

		self::$runs = 0;
		add_shortcode(
			self::SHORTCODE,
			function ( $atts ) {
				++self::$runs;

				return isset( $atts['output'] ) ? html_entity_decode( $atts['output'] ) : self::EXPANDED;
			}
		);
	}

	public function tearDown(): void {
		remove_shortcode( self::SHORTCODE );
		remove_all_filters( 'job_manager_isolate_single_job_content' );
		remove_all_filters( 'job_manager_single_job_content' );
		remove_all_actions( 'single_job_listing_end' );
		remove_all_actions( 'job_content_start' );
		remove_filter( 'the_content', [ __CLASS__, 'late_content_filter' ], 99 );
		remove_filter( 'the_content', [ __CLASS__, 'rewrite_placeholder' ], 50 );
		remove_filter( 'the_content', [ __CLASS__, 'append_to_content' ], 30 );
		unset( $_GET['wpjm_test_param'] );
		parent::tearDown();
	}

	public function data_authors() {
		return [
			'guest'         => [ false ],
			'subscriber'    => [ 'subscriber' ],
			'employer'      => [ 'employer' ],
			'administrator' => [ 'administrator' ],
		];
	}

	public function test_shortcode_in_description_of_trusted_listing_runs_once() {
		$output = $this->render_single_listing( $this->create_listing( 'administrator', 'Before [' . self::SHORTCODE . '] after' ) );

		$this->assertStringContainsString( 'Before ' . self::EXPANDED . ' after', $output );
		$this->assertSame( 1, self::$runs );
	}

	/**
	 * The description's shortcodes are expanded after the description has been through the listing's HTML filter,
	 * as they were when the content filters expanded them, so a shortcode can print markup the filter would remove.
	 */
	public function test_shortcode_output_in_trusted_description_is_not_filtered() {
		$output = $this->render_single_listing( $this->create_listing( 'administrator', '[' . self::SHORTCODE . ' output="&lt;form class=wpjm-test-form&gt;&lt;input name=a&gt;&lt;/form&gt;"]' ) );

		$this->assertStringContainsString( '<form class=wpjm-test-form><input name=a></form>', $output );
	}

	public function test_shortcode_printed_by_a_shortcode_is_not_run() {
		$output = $this->render_single_listing( $this->create_listing( 'administrator', '[' . self::SHORTCODE . ' output="&#91;' . self::SHORTCODE . '&#93;"]' ) );

		$this->assertSame( 1, self::$runs );
		$this->assertStringNotContainsString( self::EXPANDED, $output );
	}

	/**
	 * @dataProvider data_authors
	 */
	public function test_shortcode_in_listing_fields_is_never_run( $role ) {
		update_option( 'job_manager_enable_salary', '1' );

		$job_id = $this->create_listing( $role, 'Plain description.' );
		foreach ( [ '_company_name', '_company_tagline', '_job_location', '_job_salary', '_company_twitter' ] as $meta_key ) {
			update_post_meta( $job_id, $meta_key, 'Acme [' . self::SHORTCODE . '] Co' );
		}
		wp_update_post(
			[
				'ID'         => $job_id,
				'post_title' => 'Title [' . self::SHORTCODE . ']',
			]
		);

		$output = $this->render_single_listing( $job_id );

		$this->assertSame( 0, self::$runs );
		$this->assertStringNotContainsString( self::EXPANDED, $output );
	}

	/**
	 * Anything the template prints besides the description is text, whoever wrote the listing: add-ons print forms
	 * there that repeat what the visitor sent.
	 *
	 * @dataProvider data_authors
	 */
	public function test_shortcode_in_request_data_printed_by_the_template_is_never_run( $role ) {
		$_GET['wpjm_test_param'] = '[' . self::SHORTCODE . ']';

		$print = function () {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
			echo '<input name="wpjm_test_field[]" value="' . esc_attr( wp_unslash( $_GET['wpjm_test_param'] ) ) . '"><p>' . esc_html( wp_unslash( $_GET['wpjm_test_param'] ) ) . '</p>';
		};
		add_action( 'job_content_start', $print );
		add_action( 'single_job_listing_end', $print );
		add_filter(
			'job_manager_single_job_content',
			function ( $content ) {
				return $content . '<p>Appended [' . self::SHORTCODE . ']</p>';
			}
		);

		$output = $this->render_single_listing( $this->create_listing( $role, 'Plain description.' ) );

		$this->assertSame( 0, self::$runs );
		$this->assertSame( 2, substr_count( $output, '<p>[' . self::SHORTCODE . ']</p>' ) );
		$this->assertStringContainsString( '<p>Appended [' . self::SHORTCODE . ']</p>', $output );
	}

	/**
	 * A theme or plugin that expands shortcodes in post content again, later, must not reach the template either.
	 *
	 * @dataProvider data_authors
	 */
	public function test_later_content_filters_do_not_see_the_template( $role ) {
		add_filter( 'the_content', [ __CLASS__, 'late_content_filter' ], 99 );

		$job_id = $this->create_listing( $role, 'Plain description.' );
		update_post_meta( $job_id, '_company_tagline', 'Tagline [' . self::SHORTCODE . ']' );

		$output = $this->render_single_listing( $job_id );

		$this->assertSame( 0, self::$runs );
		$this->assertStringContainsString( 'Tagline', $output );
		$this->assertStringNotContainsString( 'LATE_FILTER_SAW_THE_TEMPLATE', $output );
	}

	public static function late_content_filter( $content ) {
		return do_shortcode( $content ) . ( false !== strpos( $content, 'single_job_listing' ) ? 'LATE_FILTER_SAW_THE_TEMPLATE' : '' );
	}

	public function test_content_added_by_later_filters_is_kept_in_place() {
		add_filter( 'the_content', [ __CLASS__, 'append_to_content' ], 30 );

		$output = $this->render_single_listing( $this->create_listing( 'employer', 'Plain description.' ) );

		$this->assertMatchesRegularExpression( '/^<p>Above<\/p>\s*<div class="single_job_listing">.*<\/div>\s*<p>Below<\/p>$/s', trim( $output ) );
	}

	public static function append_to_content( $content ) {
		return '<p>Above</p>' . $content . '<p>Below</p>';
	}

	public function test_placeholder_rewritten_by_a_filter_is_still_replaced() {
		add_filter( 'the_content', [ __CLASS__, 'rewrite_placeholder' ], 50 );

		$output = $this->render_single_listing( $this->create_listing( 'employer', 'Plain description.' ) );

		$this->assertStringNotContainsString( 'wpjm-single-job-', $output );
		$this->assertStringNotContainsString( 'data-test', $output );
	}

	public static function rewrite_placeholder( $content ) {
		return preg_replace( '/<div class="([^"]+)"><\/div>/', "<div data-test='1' class='\$1 extra'>\n</div>", $content );
	}

	/**
	 * @dataProvider data_authors
	 */
	public function test_no_marker_or_placeholder_is_left_in_the_page( $role ) {
		// A template that shortens the description leaves a marker without its pair.
		add_action(
			'single_job_listing_end',
			function () {
				echo esc_html( substr( wpjm_get_the_job_description(), 0, 40 ) );
				echo esc_html( substr( wpjm_get_the_job_description(), -20 ) );
			}
		);

		$output = $this->render_single_listing( $this->create_listing( $role, 'A description that is long enough to be shortened by a template.' ) );

		$this->assertStringNotContainsString( 'wpjm-description-', $output );
		$this->assertStringNotContainsString( 'wpjm-single-job-', $output );
	}

	/**
	 * Templates in themes written for older versions print the description through the filter themselves.
	 */
	public function test_description_printed_through_the_filter_by_a_template_is_expanded_for_trusted_listing() {
		add_action(
			'single_job_listing_end',
			function () {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
				echo '<div class="legacy">' . apply_filters( 'the_job_description', get_the_content() ) . '</div>';
			}
		);

		$trusted = $this->render_single_listing( $this->create_listing( 'administrator', 'Text [' . self::SHORTCODE . ']' ) );

		$this->assertSame( 2, substr_count( $trusted, 'Text ' . self::EXPANDED ) );

		self::$runs = 0;
		$this->render_single_listing( $this->create_listing( 'employer', 'Text [' . self::SHORTCODE . ']' ) );

		$this->assertSame( 0, self::$runs );
	}

	public function test_description_shortcodes_are_not_expanded_when_the_setting_removes_them() {
		add_filter( 'the_job_description', 'strip_shortcodes' );

		$output = $this->render_single_listing( $this->create_listing( 'administrator', 'Text [' . self::SHORTCODE . ']' ) );

		remove_filter( 'the_job_description', 'strip_shortcodes' );

		$this->assertSame( 0, self::$runs );
		$this->assertStringNotContainsString( '[' . self::SHORTCODE, $output );
	}

	public function test_template_is_rendered_each_time_the_content_is_printed() {
		global $wp_query;

		$this->login_as_admin();
		$wp_query = new WP_Query( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			[
				'p'         => $this->create_listing( 'employer', 'Plain description.' ),
				'post_type' => \WP_Job_Manager_Post_Types::PT_LISTING,
			]
		);
		$wp_query->the_post();

		ob_start();
		the_content();
		the_content();
		$output = ob_get_clean();
		wp_reset_postdata();

		$this->assertSame( 2, substr_count( $output, '<div class="single_job_listing">' ) );
		$this->assertStringNotContainsString( 'wpjm-single-job-', $output );
	}

	public function test_images_in_the_template_are_still_prepared_for_display() {
		$output = $this->render_single_listing( $this->create_listing( 'administrator', '<img src="https://example.com/a.png" width="800" height="600" alt="">' ) );

		$this->assertMatchesRegularExpression( '/<img[^>]+decoding="async"[^>]+src="https:\/\/example\.com\/a\.png"|<img[^>]+src="https:\/\/example\.com\/a\.png"[^>]+decoding="async"/', $output );
	}

	public function test_media_written_by_untrusted_author_is_still_removed() {
		$job_id = $this->create_listing( 'employer', 'Plain description.' );

		add_action(
			'single_job_listing_end',
			function () {
				echo '<video class="wp-video-shortcode" src="https://example.com/a.mp4"></video>';
			}
		);

		$this->assertStringNotContainsString( '<video', $this->render_single_listing( $job_id ) );
	}

	/**
	 * With the template passed through the content filters, as before 2.4.12, everything in it is searched.
	 */
	public function test_template_passes_through_the_content_filters_when_filtered_off() {
		add_filter( 'job_manager_isolate_single_job_content', '__return_false' );
		add_filter( 'the_content', [ __CLASS__, 'late_content_filter' ], 99 );

		$job_id = $this->create_listing( 'administrator', 'Text [' . self::SHORTCODE . ']' );
		update_post_meta( $job_id, '_company_tagline', 'Tagline [' . self::SHORTCODE . ']' );

		$output = $this->render_single_listing( $job_id );

		$this->assertSame( 2, self::$runs );
		$this->assertStringContainsString( 'LATE_FILTER_SAW_THE_TEMPLATE', $output );
		$this->assertStringNotContainsString( 'wpjm-description-', $output );
	}

	private function create_listing( $role, $description ) {
		global $wpdb;

		$job_id = $this->factory->job_listing->create(
			[
				'post_author' => $role ? $this->factory->user->create( [ 'role' => $role ] ) : 0,
				'meta_input'  => [
					'_company_name'    => 'Acme',
					'_company_tagline' => 'A fine company',
					'_job_location'    => 'Sydney',
				],
			]
		);

		// Written directly, as content saved by an earlier version would be.
		$wpdb->update( $wpdb->posts, [ 'post_content' => $description ], [ 'ID' => $job_id ] );
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
