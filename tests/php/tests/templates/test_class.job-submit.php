<?php
/**
 * Covers templates/job-submit.php.
 *
 * @package wp-job-manager
 */
class WP_Test_Job_Submit_Template extends WPJM_BaseTest {

	/**
	 * reCAPTCHA version set on the singleton before the test.
	 *
	 * @var string
	 */
	private $original_recaptcha_version;

	public function setUp(): void {
		parent::setUp();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->original_recaptcha_version = WP_Job_Manager\WP_Job_Manager_Recaptcha::instance()->get_recaptcha_version();
	}

	public function tearDown(): void {
		$this->set_recaptcha_version( $this->original_recaptcha_version );
		parent::tearDown();
	}

	/**
	 * The version is read from the options once, when the singleton is built, so set it on the instance directly.
	 *
	 * @param string $version reCAPTCHA version.
	 */
	private function set_recaptcha_version( $version ) {
		$property = new ReflectionProperty( WP_Job_Manager\WP_Job_Manager_Recaptcha::class, 'recaptcha_version' );
		$property->setAccessible( true );
		$property->setValue( WP_Job_Manager\WP_Job_Manager_Recaptcha::instance(), $version );
	}

	/**
	 * Render the job-submit template and return the Save Draft button markup.
	 *
	 * @return string
	 */
	private function render_save_draft_button() {
		ob_start();
		get_job_manager_template(
			'job-submit.php',
			[
				'form'               => 'submit-job',
				'job_id'             => 0,
				'resume_edit'        => false,
				'action'             => '',
				'job_fields'         => [],
				'company_fields'     => [],
				'step'               => 0,
				'can_continue_later' => true,
				'submit_button_text' => 'Preview',
			]
		);
		$html = ob_get_clean();

		$this->assertSame( 1, preg_match( '/<input[^>]*name="save_draft"[^>]*>/', $html, $matches ) );

		return $matches[0];
	}

	/**
	 * With reCAPTCHA v3 the Save Draft button must fetch a token too, or the draft fails the reCAPTCHA check.
	 */
	public function test_save_draft_button_uses_recaptcha_v3_handler() {
		$this->set_recaptcha_version( 'v3' );

		$this->assertStringContainsString( 'onclick="jm_job_submit_click(event)"', $this->render_save_draft_button() );
	}

	/**
	 * Other reCAPTCHA versions submit the draft as a normal form post.
	 */
	public function test_save_draft_button_has_no_handler_without_recaptcha_v3() {
		$this->set_recaptcha_version( 'v2' );

		$this->assertStringNotContainsString( 'onclick', $this->render_save_draft_button() );
	}
}
