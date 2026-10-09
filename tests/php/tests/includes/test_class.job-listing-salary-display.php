<?php
/**
 * Regression tests for showing the job salary on the job listings loop, gated by the
 * `job_manager_show_salary_in_listings` option.
 *
 * @package wp-job-manager/tests
 */

class Tests_Job_Listing_Salary_Display extends WPJM_BaseTest {

	public function setUp(): void {
		parent::setUp();
		update_option( 'job_manager_enable_salary', 1 );
	}

	public function tearDown(): void {
		delete_option( 'job_manager_enable_salary' );
		delete_option( 'job_manager_show_salary_in_listings' );
		parent::tearDown();
	}

	/**
	 * @covers ::the_job_salary
	 */
	public function test_salary_hidden_from_listings_by_default() {
		$post_id = $this->factory->job_listing->create( [ 'meta_input' => [ '_job_salary' => '55000' ] ] );
		delete_option( 'job_manager_show_salary_in_listings' );

		$html = $this->render_job_listing_loop( $post_id );

		$this->assertStringNotContainsString( 'class="salary"', $html );
	}

	/**
	 * @covers ::the_job_salary
	 */
	public function test_salary_shown_in_listings_when_enabled() {
		$post_id = $this->factory->job_listing->create( [ 'meta_input' => [ '_job_salary' => '55000' ] ] );
		update_option( 'job_manager_show_salary_in_listings', 1 );

		$html = $this->render_job_listing_loop( $post_id );

		$this->assertStringContainsString( 'class="salary"', $html );
		$this->assertStringContainsString( '55000', $html );
	}

	/**
	 * @covers ::the_job_salary
	 *
	 * Sanity: enabling the listings setting must not print an empty <li> for a listing
	 * that has no salary value set.
	 */
	public function test_no_salary_markup_when_listing_has_no_salary_value() {
		$post_id = $this->factory->job_listing->create();
		update_option( 'job_manager_show_salary_in_listings', 1 );

		$html = $this->render_job_listing_loop( $post_id );

		$this->assertStringNotContainsString( 'class="salary"', $html );
	}

	/**
	 * Renders the job listing loop template for a single post, mirroring how the `[jobs]`
	 * shortcode outputs each card via `get_job_manager_template_part( 'content', 'job_listing' )`.
	 */
	private function render_job_listing_loop( $post_id ) {
		global $post;
		$post = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		ob_start();
		setup_postdata( $post );
		get_job_manager_template_part( 'content', 'job_listing' );
		wp_reset_postdata();

		return (string) ob_get_clean();
	}
}
