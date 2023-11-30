<?php
/**
 * Frontend UI elements of Job Manager.
 *
 * @package wp-job-manager
 * @since $$next-version$$
 */

namespace WP_Job_Manager\UI;

use WP_Job_Manager\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once JOB_MANAGER_PLUGIN_DIR . '/includes/ui/Notice.php';

/**
 * Frontend UI elements of Job Manager.
 *
 * @since $$next-version$$
 *
 * @internal This API is still under development and subject to change.
 * @access private
 */
class UI {

	use Singleton;

	/**
	 * Whether the UI styles should be loaded the page.
	 *
	 * @var bool
	 */
	private $has_ui = false;

	/**
	 * Instance constructor
	 */
	private function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
	}

	/**
	 * Register and enqueue styles.
	 *
	 * @access private
	 */
	public function enqueue_styles() {
		\WP_Job_Manager::register_style( 'wp-job-manager-ui', 'css/ui.css', [] );

		if ( $this->has_ui ) {
			wp_enqueue_style( 'wp-job-manager-ui' );
		}
	}

	/**
	 * Request the styles to be loaded for the page.
	 */
	public static function ensure_styles() {
		self::instance()->has_ui = true;
	}
}

UI::instance();
