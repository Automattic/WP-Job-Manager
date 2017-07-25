<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Handles front admin page for WP Job Manager.
 *
 * @package wp-job-manager
 * @see https://github.com/woocommerce/woocommerce/blob/3.0.8/includes/admin/class-wc-admin-permalink-settings.php  Based on WooCommerce's implementation.
 * @since 1.27.0
 */
class WP_Job_Manager_Permalink_Settings {
	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  1.27.0
	 */
	private static $_instance = null;

	/**
	 * Permalink settings.
	 *
	 * @var array
	 * @since 1.27.0
	 */
	private $permalinks = array();

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  1.27.0
	 * @static
	 * @return self Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->setup_fields();
		$this->settings_save();
		$this->permalinks = WP_Job_Manager_Post_Types::get_permalink_structure();
	}

	public function setup_fields() {
		add_settings_field(
			'wpjm_job_base_slug',
			__( 'Job base', 'wp-job-manager' ),
			array( $this, 'job_base_slug_input' ),
			'permalink',
			'optional'
		);
		add_settings_field(
			'wpjm_job_category_slug',
			__( 'Job category base', 'wp-job-manager' ),
			array( $this, 'job_category_slug_input' ),
			'permalink',
			'optional'
		);
		add_settings_field(
			'wpjm_job_type_slug',
			__( 'Job type base', 'wp-job-manager' ),
			array( $this, 'job_type_slug_input' ),
			'permalink',
			'optional'
		);
	}

	/**
	 * Show a slug input box for job post type slug.
	 */
	public function job_base_slug_input() {
		?>
		<input name="wpjm_job_base_slug" type="text" class="regular-text code" value="<?php echo esc_attr( $this->permalinks['job_base'] ); ?>" placeholder="<?php echo esc_attr_x( 'job', 'Job permalink - resave permalinks after changing this', 'wp-job-manager' ) ?>" />
		<?php
	}

	/**
	 * Show a slug input box for job category slug.
	 */
	public function job_category_slug_input() {
		?>
		<input name="wpjm_job_category_slug" type="text" class="regular-text code" value="<?php echo esc_attr( $this->permalinks['category_base'] ); ?>" placeholder="<?php echo esc_attr_x( 'job-category', 'Job category slug - resave permalinks after changing this', 'wp-job-manager' ) ?>" />
		<?php
	}

	/**
	 * Show a slug input box for job type slug.
	 */
	public function job_type_slug_input() {
		?>
		<input name="wpjm_job_type_slug" type="text" class="regular-text code" value="<?php echo esc_attr( $this->permalinks['type_base'] ); ?>" placeholder="<?php echo esc_attr_x( 'job-type', 'Job type slug - resave permalinks after changing this', 'wp-job-manager' ) ?>" />
		<?php
	}

	/**
	 * Save the settings.
	 */
	public function settings_save() {
		if ( ! is_admin() ) {
			return;
		}

		if ( isset( $_POST['permalink_structure'] ) ) {
			if ( function_exists( 'switch_to_locale' ) ) {
				switch_to_locale( get_locale() );
			}

			$permalinks                   = (array) get_option( 'wpjm_permalinks', array() );
			$permalinks['job_base']       = sanitize_title_with_dashes( $_POST['wpjm_job_base_slug'] );
			$permalinks['category_base']  = sanitize_title_with_dashes( $_POST['wpjm_job_category_slug'] );
			$permalinks['type_base']      = sanitize_title_with_dashes( $_POST['wpjm_job_type_slug'] );

			update_option( 'wpjm_permalinks', $permalinks );

			if ( function_exists( 'restore_current_locale' ) ) {
				restore_current_locale();
			}
		}
	}
}

WP_Job_Manager_Permalink_Settings::instance();
