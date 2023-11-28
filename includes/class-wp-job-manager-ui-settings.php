<?php
/**
 * Handles Job Manager's Gutenberg Blocks.
 *
 * @package wp-job-manager
 * @since 1.32.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Preview and settings for UI elements.
 *
 * @access private
 *
 * @internal
 */
class WP_Job_Manager_Ui_Settings {
	/**
	 * The static instance of the WP_Job_Manager_Ui
	 *
	 * @var self
	 */
	private static $instance = null;

	/**
	 * Singleton instance getter
	 *
	 * @return self
	 */
	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Instance constructor
	 */
	private function __construct() {
		add_shortcode( 'job_manager_ui_test', [ $this, 'preview_ui_elements' ] );

	}

	/**
	 * Output previews of UI elements in various scenarios.
	 *
	 * @access private
	 *
	 * @return string Generated HTML of UI elements.
	 */
	public function preview_ui_elements() {
		$elements = [];

		$elements[] = WP_Job_Manager_Ui::notice(
			[
				'title'        => 'Job listing active',
				'message_icon' => 'info',
				'classes'      => [ 'type-strong' ],
				'message'      => 'Expires  in 25 days.',
			]
		);

		$elements[] = WP_Job_Manager_Ui::notice(
			[
				'message' => 'This is a test notice message with a checkmark.',
				'icon'    => 'check',
			]
		);

		$elements[] = WP_Job_Manager_Ui::notice(
			[
				'message' => 'This is a test notice message.',
			]
		);

		$elements[] = WP_Job_Manager_Ui::notice(
			[
				'classes' => [ 'type-error' ],
				'message' => 'This is a test notice message with an alert icon and red colors.',
				'icon'    => 'alert',
			]
		);

		$elements[] = WP_Job_Manager_Ui::notice(
			[
				'classes' => [ 'type-success' ],
				'message' => 'This is a super green test notice message.',
				'icon'    => 'check',
			]
		);

		$elements[] = WP_Job_Manager_Ui::notice(
			[
				'classes' => [ 'type-info' ],
				'message' => 'This is an informational blue message.',
				'icon'    => 'info',
			]
		);

		$elements[] = WP_Job_Manager_Ui::notice(
			[
				'title' => 'Test Notice Created',
				'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
  <path stroke="#1E1E1E" stroke-width="1.5" d="M19.25 19.25v-9.5H4.75v9.5z"/>
  <path fill="#1E1E1E" fill-rule="evenodd" d="M13 19V9h-1.5v10H13Z" clip-rule="evenodd"/>
  <path stroke="#1E1E1E" stroke-width="1.5" d="M16.5 6.5c0 .97-.78 1.75-1.75 1.75H13V6.5a1.75 1.75 0 1 1 3.5 0ZM8 6.5c0 .97.78 1.75 1.75 1.75h1.75V6.5a1.75 1.75 0 1 0-3.5 0Z"/>
</svg>
',
			]
		);

		$elements[] = WP_Job_Manager_Ui::notice(
			[
				'title'   => 'Test Notice Created',
				'icon'    => 'check',
				'classes' => [ 'large' ],
				'message' => 'This is a test Job Manager notice. Should be large with a checkmark.',
				'buttons' => [
					[
						'text' => 'Primary Button',
						'href' => '/',
					],
					[
						'text' => 'Secondary Button',
						'href' => '/',
					],
				],
				'links'   => [
					[
						'text' => 'Action',
						'href' => '/',
					],
					[
						'text' => 'Action',
						'href' => '/',
					],
				],
			]
		);

		$elements[] = WP_Job_Manager_Ui::notice(
			[
				'title'   => 'Test Notice Created',
				'message' => 'This is a test Job Manager notice. Should be large with a checkmark.',
				'buttons' => [
					[
						'text'    => 'Light Button',
						'href'    => '/',
						'primary' => false,
					],
				],
				'links'   => [
					[
						'text' => 'Action',
						'href' => '/',
					],
				],
			]
		);

		$elements[] = WP_Job_Manager_Ui::notice(
			[
				'title'   => 'Test Notice Created',
				'message' => 'This is a test Job Manager notice.',
				'links'   => [
					[
						'text' => 'Action',
						'href' => '/',
					],
					[
						'text' => 'Action',
						'href' => '/',
					],
				],
			]
		);

		$elements[] = WP_Job_Manager_Ui::notice(
			[
				'title'   => 'Test Notice Created',
				'icon'    => 'check',
				'message' => 'This is a test Job Manager notice. Should be large with a checkmark.',
				'buttons' => [
					[
						'text' => 'Primary Button',
						'href' => '/',
					],
				],
			]
		);

		$elements[] = WP_Job_Manager_Ui::notice(
			[
				'icon'    => 'check',
				'message' => 'This is a test Job Manager notice, with an icon, message and an action.',
				'links'   => [
					[
						'text' => 'Action',
						'href' => '/',
					],
				],
			]
		);

		$elements[] = WP_Job_Manager_Ui::notice(
			[
				'icon'    => 'check',
				'classes' => [ 'alignwide' ],
				'message' => 'This is a test Job Manager notice, with an icon, message and an action. Actions on the side.',
				'links'   => [
					[
						'text' => 'Action',
						'href' => '/',
					],
				],
			]
		);

		$elements[] = WP_Job_Manager_Ui::notice(
			[
				'message' => 'This is a test Job Manager notice, with only a message and an action.',
				'links'   => [
					[
						'text' => 'Action',
						'href' => '/',
					],
				],
			]
		);

		$elements[] = WP_Job_Manager_Ui::notice(
			[
				'message' => 'This is a test Job Manager notice. Should be large with a checkmark.',
				'buttons' => [
					[
						'text' => 'Primary Button',
						'href' => '/',
					],
				],
			]
		);

		$elements[] = WP_Job_Manager_Ui::notice(
			[
				'message' => 'This is a test Job Manager notice. Should be large with a checkmark.',
				'buttons' => [
					[
						'text'    => 'Outlined Button',
						'href'    => '/',
						'primary' => false,
					],
				],
			]
		);

		return implode( '', $elements );

	}
}

WP_Job_Manager_Ui_Settings::instance();
