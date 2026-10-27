<?php
/**
 * Regression tests for the `[jobs]` shortcode in block templates.
 *
 * Block themes run do_shortcode() before do_blocks() when rendering a template
 * (see WordPress core get_the_block_template_html()), so the core/shortcode
 * block's wpautop() call runs on our already-rendered listing markup instead
 * of the raw shortcode tag. That injected stray p/br tags into the output.
 *
 * @see https://github.com/Automattic/WP-Job-Manager/issues/2521
 */
class WP_Test_WP_Job_Manager_Shortcodes extends WPJM_BaseTest {

	public function setUp(): void {
		parent::setUp();
		$this->factory->job_listing->create(
			[
				'post_title'  => 'Shortcode Test Listing',
				'post_status' => 'publish',
			]
		);
	}

	/**
	 * Expanded [jobs] markup inside a core/shortcode block must skip wpautop.
	 *
	 * @covers WP_Job_Manager_Shortcodes::protect_jobs_shortcode_from_wpautop
	 * @covers WP_Job_Manager_Shortcodes::output_jobs
	 */
	public function test_jobs_shortcode_block_does_not_inject_paragraphs() {
		// Simulate the block-template pipeline: shortcode expands first.
		$expanded = do_shortcode( '[jobs show_filters=false]' );

		$this->assertStringContainsString( 'data-wp-job-manager-jobs-shortcode="1"', $expanded );

		// Then the core/shortcode block renders the already-expanded HTML.
		$block = [
			'blockName'    => 'core/shortcode',
			'attrs'        => [],
			'innerBlocks'  => [],
			'innerHTML'    => $expanded,
			'innerContent' => [ $expanded ],
		];

		$output = render_block( $block );

		// Without the fix, wpautop() leaves stray </p>/<p> inside the listing card.
		$this->assertStringNotContainsString( '</p></div>', $output );
		$this->assertStringNotContainsString( '<p> </a>', $output );
		$this->assertStringNotContainsString( '<p></a>', $output );
		$this->assertStringContainsString( 'Shortcode Test Listing', $output );
		$this->assertStringContainsString( 'class="job_listings"', $output );
		$this->assertStringContainsString( 'data-wp-job-manager-jobs-shortcode="1"', $output );
	}

	/**
	 * Colliding job_listings class without our marker must not short-circuit.
	 *
	 * @covers WP_Job_Manager_Shortcodes::protect_jobs_shortcode_from_wpautop
	 */
	public function test_unrelated_job_listings_markup_is_not_short_circuited() {
		$shortcodes = WP_Job_Manager_Shortcodes::instance();

		$result = $shortcodes->protect_jobs_shortcode_from_wpautop(
			null,
			[
				'blockName' => 'core/shortcode',
				'innerHTML' => '<div class="job_listings" data-show_filters="false"></div>',
			]
		);

		$this->assertNull( $result );
	}

	/**
	 * Unrelated shortcode blocks still go through core's normal render path.
	 *
	 * @covers WP_Job_Manager_Shortcodes::protect_jobs_shortcode_from_wpautop
	 */
	public function test_unrelated_shortcode_block_is_not_short_circuited() {
		$shortcodes = WP_Job_Manager_Shortcodes::instance();
		$html       = '[gallery]';

		$result = $shortcodes->protect_jobs_shortcode_from_wpautop(
			null,
			[
				'blockName' => 'core/shortcode',
				'innerHTML' => $html,
			]
		);

		$this->assertNull( $result );
	}

	/**
	 * Filter leaves non-shortcode blocks alone.
	 *
	 * @covers WP_Job_Manager_Shortcodes::protect_jobs_shortcode_from_wpautop
	 */
	public function test_non_shortcode_block_is_not_short_circuited() {
		$shortcodes = WP_Job_Manager_Shortcodes::instance();

		$result = $shortcodes->protect_jobs_shortcode_from_wpautop(
			null,
			[
				'blockName' => 'core/paragraph',
				'innerHTML' => '<div class="job_listings" data-show_filters="false"></div>',
			]
		);

		$this->assertNull( $result );
	}

	/**
	 * Marker appearing in body text (after the first '>') must not short-circuit.
	 *
	 * Employer-supplied listing titles or company names land in the block HTML,
	 * so the marker must be anchored to the opening tag of our own wrapper.
	 *
	 * @covers WP_Job_Manager_Shortcodes::protect_jobs_shortcode_from_wpautop
	 */
	public function test_marker_in_body_text_does_not_short_circuit() {
		$shortcodes = WP_Job_Manager_Shortcodes::instance();

		// Marker sits in the visible body text, not in any opening attribute.
		$html = '<div class="job_listings"><p>data-wp-job-manager-jobs-shortcode="1"</p></div>';

		$result = $shortcodes->protect_jobs_shortcode_from_wpautop(
			null,
			[
				'blockName' => 'core/shortcode',
				'innerHTML' => $html,
			]
		);

		$this->assertNull( $result );
	}
}
