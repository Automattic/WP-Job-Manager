<?php
/**
 * Tests for loading the edit job form inside the job dashboard overlay.
 *
 * @package wp-job-manager
 */

use WP_Job_Manager\Job_Overlay;

class WP_Test_Job_Overlay_Edit extends WPJM_BaseTest {

	public function setUp(): void {
		parent::setUp();
		$_GET = [];
		delete_option( 'job_manager_job_dashboard_page_id' );
	}

	public function tearDown(): void {
		unset( $GLOBALS['show_admin_bar'] );
		delete_option( 'job_manager_job_dashboard_page_id' );
		$_GET = [];
		parent::tearDown();
	}

	/**
	 * Creates a page and navigates the test request to it with the given query args.
	 *
	 * @param string $content Page content.
	 * @param array  $args    Query args.
	 */
	private function go_to_page( $content, $args ) {
		$page_id = $this->factory->post->create(
			[
				'post_type'    => 'page',
				'post_content' => $content,
			]
		);

		$this->go_to( add_query_arg( $args, get_permalink( $page_id ) ) );
	}

	public function test_overlay_request_on_dashboard_page_uses_bare_template() {
		$this->login_as_employer();
		$this->go_to_page(
			'[job_dashboard]',
			[
				'action'     => 'edit',
				'job_id'     => 1,
				'jm_overlay' => 1,
			]
		);

		$this->assertTrue( Job_Overlay::is_edit_job_overlay_request() );

		Job_Overlay::instance()->init_edit_job_overlay();

		$template = apply_filters( 'template_include', 'page.php' );
		$this->assertStringEndsWith( 'job-dashboard-edit-overlay.php', $template );
		$this->assertFileExists( $template );
		$this->assertFalse( is_admin_bar_showing() );
	}

	public function test_overlay_request_on_configured_dashboard_page() {
		$this->login_as_employer();
		$this->go_to_page(
			'<!-- wp:block {"ref":7} /-->',
			[
				'action'     => 'edit',
				'jm_overlay' => 1,
			]
		);
		update_option( 'job_manager_job_dashboard_page_id', get_queried_object_id() );

		$this->assertTrue( Job_Overlay::is_edit_job_overlay_request() );
	}

	public function test_edit_without_overlay_flag_keeps_theme_template() {
		$this->login_as_employer();
		$this->go_to_page( '[job_dashboard]', [ 'action' => 'edit' ] );

		$this->assertFalse( Job_Overlay::is_edit_job_overlay_request() );

		Job_Overlay::instance()->init_edit_job_overlay();

		$this->assertSame( 'page.php', apply_filters( 'template_include', 'page.php' ) );
	}

	public function test_other_action_is_not_overlay_request() {
		$this->go_to_page(
			'[job_dashboard]',
			[
				'action'     => 'mark_filled',
				'jm_overlay' => 1,
			]
		);

		$this->assertFalse( Job_Overlay::is_edit_job_overlay_request() );
	}

	public function test_overlay_flag_ignored_outside_dashboard_page() {
		$this->go_to_page(
			'No dashboard here.',
			[
				'action'     => 'edit',
				'jm_overlay' => 1,
			]
		);

		$this->assertFalse( Job_Overlay::is_edit_job_overlay_request() );
	}
}
