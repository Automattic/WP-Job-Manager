<?php
/**
 * Tests the job submission confirmation template.
 *
 * @package wp-job-manager
 */
class WP_Test_Job_Submitted_Template extends WPJM_BaseTest {

	public function tearDown(): void {
		delete_option( 'job_manager_job_dashboard_page_id' );
		parent::tearDown();
	}

	/**
	 * Render the job submission confirmation template for a listing.
	 *
	 * @param int $job_id Listing ID.
	 * @return string Rendered template output.
	 */
	private function render_template( $job_id ) {
		ob_start();
		get_job_manager_template( 'job-submitted.php', [ 'job' => get_post( $job_id ) ] );
		return ob_get_clean();
	}

	/**
	 * A published submission links the employer to the configured dashboard.
	 */
	public function test_published_submission_links_to_job_dashboard() {
		$this->login_as_employer();
		$job_id = $this->factory->job_listing->create( [ 'post_status' => 'publish' ] );
		$page_id = $this->factory->post->create(
			[
				'post_type'  => 'page',
				'post_title' => 'Job Dashboard',
			]
		);
		update_option( 'job_manager_job_dashboard_page_id', $page_id );

		$output = $this->render_template( $job_id );

		$this->assertStringContainsString( 'To manage your listings', $output );
		$this->assertStringContainsString( 'href="' . esc_url( get_permalink( $page_id ) ) . '"', $output );
	}

	/**
	 * A published submission does not render a broken dashboard link when no page is configured.
	 */
	public function test_published_submission_without_dashboard_page_has_no_dashboard_link() {
		$this->login_as_employer();
		$job_id = $this->factory->job_listing->create( [ 'post_status' => 'publish' ] );

		$output = $this->render_template( $job_id );

		$this->assertStringNotContainsString( 'To manage your listings', $output );
	}
}
