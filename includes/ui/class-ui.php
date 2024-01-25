<?php
/**
 * Frontend UI elements of Job Manager.
 *
 * @package wp-job-manager
 * @since 2.2.0
 */

namespace WP_Job_Manager\UI;

use WP_Job_Manager\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once JOB_MANAGER_PLUGIN_DIR . '/includes/ui/class-ui-elements.php';
require_once JOB_MANAGER_PLUGIN_DIR . '/includes/ui/class-notice.php';
require_once JOB_MANAGER_PLUGIN_DIR . '/includes/ui/class-modal-dialog.php';
require_once JOB_MANAGER_PLUGIN_DIR . '/includes/ui/class-redirect-message.php';

/**
 * Frontend UI elements of Job Manager.
 *
 * @since 2.2.0
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
	private bool $has_ui;

	/**
	 * An array of css variables to be enqueued with the styles.
	 *
	 * @var array
	 */
	private array $css_variables;

	/**
	 * Instance constructor
	 */
	private function __construct() {
		$this->has_ui        = false;
		$this->css_variables = [];
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

			if ( ! empty( $this->css_variables ) ) {
				wp_add_inline_style( 'wp-job-manager-ui', $this->generate_inline_css() );
			}
		}
	}

	/**
	 * Request the styles to be loaded for the page.
	 *
	 * @param array $css_variables An array of CSS variables to be enqueued: <variable_name> => <value>.
	 */
	public static function ensure_styles( array $css_variables = [] ) {
		self::instance()->has_ui        = true;
		self::instance()->css_variables = array_merge( self::instance()->css_variables, $css_variables );

		if ( did_action( 'wp_enqueue_scripts' ) ) {
			self::instance()->enqueue_styles();
		}
	}

	/**
	 * Generates CSS to be inlined with the UI styles.
	 *
	 * @return string The CSS.
	 */
	private function generate_inline_css() {

		$css = ':root{';

		foreach ( $this->css_variables as $name => $value ) {
			$css .= esc_attr( $name ) . ': ' . esc_attr( $value ) . ';';
		}

		$css .= '}';

		return $css;
	}
}

UI::instance();
