<?php
/**
 * Preview and settings for UI elements.
 *
 * @package wp-job-manager
 * @since $$next-version$$
 */

namespace WP_Job_Manager\UI;

use WP_Job_Manager\Singleton;

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
class Settings {

	use Singleton;

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

		$elements[] = Notice::success( 'Notice rendered successfully.' );
		$elements[] = Notice::error( 'Invalid notice message.' );

		$elements[] = Notice::render(
			[
				'title'        => 'Job listing active',
				'message_icon' => 'info',
				'classes'      => [ 'color-strong' ],
				'message'      => 'Expires  in 25 days.',
			]
		);

		$elements[] = Notice::render(
			[
				'message' => 'This is a test notice message with a checkmark.',
				'icon'    => 'check',
			]
		);

		$elements[] = Notice::render(
			[
				'message' => 'This is a test notice message.',
			]
		);

		$elements[] = Notice::render(
			[
				'classes' => [ 'type-hint' ],
				'message' => 'Already have an account?',
				'links'   => [
					[
						'text' => 'Sign In',
						'href' => '/',
					],
				],
			]
		);

		$elements[] = Notice::render(
			[
				'classes' => [ 'color-error' ],
				'message' => 'This is a test notice message with an alert icon and red colors.',
				'icon'    => 'alert',
			]
		);

		$elements[] = Notice::render(
			[
				'classes' => [ 'color-success' ],
				'message' => 'This is a super green test notice message.',
				'icon'    => 'check',
			]
		);

		$elements[] = Notice::render(
			[
				'classes' => [ 'color-info' ],
				'message' => 'This is an informational blue message.',
				'icon'    => 'info',
			]
		);

		$elements[] = Notice::render(
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

		$elements[] = Notice::render(
			[
				'title'   => 'Test Notice Created',
				'icon'    => 'check',
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

		$elements[] = Notice::render(
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

		$elements[] = Notice::render(
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

		$elements[] = Notice::render(
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

		$elements[] = Notice::render(
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

		$elements[] = Notice::render(
			[
				'icon'    => 'check',
				'classes' => [ 'alignwide' ],
				'message' => 'This is a wide Job Manager notice, with an icon, message and an action. Actions are on the side.',
				'links'   => [
					[
						'text' => 'Action',
						'href' => '/',
					],
				],
			]
		);

		$elements[] = Notice::render(
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

		$elements[] = Notice::render(
			[
				'message' => 'This is a test Job Manager notice.',
				'buttons' => [
					[
						'text' => 'Primary Button',
						'href' => '/',
					],
				],
			]
		);

		$elements[] = Notice::render(
			[
				'message' => 'This is a test Job Manager notice.',
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

Settings::instance();
