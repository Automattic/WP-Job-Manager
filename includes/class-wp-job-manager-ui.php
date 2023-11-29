<?php
/**
 * Frontend UI elements of Job Manager.
 *
 * @package wp-job-manager
 * @since $$next-version$$
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Frontend UI elements of Job Manager.
 *
 * @since $$next-version$$
 *
 * @internal This API is still under development and subject to change.
 * @access private
 */
class WP_Job_Manager_UI {

	/**
	 * The singleton instance of the class.
	 *
	 * @var self
	 */
	private static $instance = null;

	/**
	 * Whether the UI styles should be loaded the page.
	 *
	 * @var bool
	 */
	private $has_ui = false;

	/**
	 * Singleton instance getter.
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
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
	}

	/**
	 * Generate a notice. See WP_Job_Manager_Ui_Notice::notice for details.
	 *
	 * @param array $options Notice options.
	 *
	 * @return string Notice HTML.
	 */
	public static function notice( $options ) {
		self::ensure_styles();

		return WP_Job_Manager_UI_Notice::notice( $options );
	}

	/**
	 * Register and enqueue styles.
	 *
	 * @access private
	 */
	public function enqueue_styles() {
		WP_Job_Manager::register_style( 'wp-job-manager-ui', 'css/ui.css', [] );

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

WP_Job_Manager_UI::instance();
