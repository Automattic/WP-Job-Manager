<?php
/**
 * File containing the class WP_Job_Manager_Permalink_Settings.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles front admin page for WP Job Manager.
 *
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
	private static $instance = null;

	/**
	 * Permalink settings.
	 *
	 * @var array
	 * @since 1.27.0
	 */
	private $permalinks = [];

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  1.27.0
	 * @static
	 * @return self Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->setup_fields();
		$this->settings_save();
		$this->permalinks = WP_Job_Manager_Post_Types::get_permalink_structure();
	}

	/**
	 * Add setting fields related to permalinks.
	 */
	public function setup_fields() {
		add_settings_field(
			'wpjm_job_base_slug',
			__( 'Job base', 'wp-job-manager' ),
			[ $this, 'job_base_slug_input' ],
			'permalink',
			'optional'
		);
		add_settings_field(
			'wpjm_job_category_slug',
			__( 'Job category base', 'wp-job-manager' ),
			[ $this, 'job_category_slug_input' ],
			'permalink',
			'optional'
		);
		add_settings_field(
			'wpjm_job_type_slug',
			__( 'Job type base', 'wp-job-manager' ),
			[ $this, 'job_type_slug_input' ],
			'permalink',
			'optional'
		);
		if ( current_theme_supports( 'job-manager-templates' ) ) {
			add_settings_field(
				'wpjm_job_listings_archive_slug',
				__( 'Job listing archive page', 'wp-job-manager' ),
				[ $this, 'job_listings_archive_slug_input' ],
				'permalink',
				'optional'
			);
		}
	}

	/**
	 * Show a slug input box for job listing archive slug.
	 */
	public function job_listings_archive_slug_input() {
		?>
		<input name="wpjm_job_listings_archive_slug" type="text" class="regular-text code" value="<?php echo esc_attr( $this->permalinks['jobs_archive'] ); ?>" placeholder="<?php echo esc_attr( $this->permalinks['jobs_archive_rewrite_slug'] ); ?>" />
		<?php
	}

	/**
	 * Show a slug input box for job post type slug.
	 */
	public function job_base_slug_input() {
		?>
		<input name="wpjm_job_base_slug" type="text" class="regular-text code" value="<?php echo esc_attr( $this->permalinks['job_base'] ); ?>" placeholder="<?php echo esc_attr_x( 'job', 'Job permalink - resave permalinks after changing this', 'wp-job-manager' ); ?>" />
		<?php
	}

	/**
	 * Show a slug input box for job category slug.
	 */
	public function job_category_slug_input() {
		?>
		<input name="wpjm_job_category_slug" type="text" class="regular-text code" value="<?php echo esc_attr( $this->permalinks['category_base'] ); ?>" placeholder="<?php echo esc_attr_x( 'job-category', 'Job category slug - resave permalinks after changing this', 'wp-job-manager' ); ?>" />
		<?php
	}

	/**
	 * Show a slug input box for job type slug.
	 */
	public function job_type_slug_input() {
		?>
		<input name="wpjm_job_type_slug" type="text" class="regular-text code" value="<?php echo esc_attr( $this->permalinks['type_base'] ); ?>" placeholder="<?php echo esc_attr_x( 'job-type', 'Job type slug - resave permalinks after changing this', 'wp-job-manager' ); ?>" />
		<?php
	}

	/**
	 * Save the settings.
	 */
	public function settings_save() {
		if ( ! is_admin() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WP core handles nonce check for settings save.
		if ( ! isset( $_POST['permalink_structure'] ) ) {
			// We must not be saving permalinks.
			return;
		}

		check_admin_referer( 'update-permalink' );

		if ( function_exists( 'switch_to_locale' ) ) {
			switch_to_locale( get_locale() );
		}

		$permalink_settings = WP_Job_Manager_Post_Types::get_raw_permalink_settings();

		$permalink_settings['job_base']      = isset( $_POST['wpjm_job_base_slug'] ) ? sanitize_title_with_dashes( wp_unslash( $_POST['wpjm_job_base_slug'] ) ) : '';
		$permalink_settings['category_base'] = isset( $_POST['wpjm_job_category_slug'] ) ? sanitize_title_with_dashes( wp_unslash( $_POST['wpjm_job_category_slug'] ) ) : '';
		$permalink_settings['type_base']     = isset( $_POST['wpjm_job_type_slug'] ) ? sanitize_title_with_dashes( wp_unslash( $_POST['wpjm_job_type_slug'] ) ) : '';

		if ( isset( $_POST['wpjm_job_listings_archive_slug'] ) ) {
			$permalink_settings['jobs_archive'] = sanitize_title_with_dashes( wp_unslash( $_POST['wpjm_job_listings_archive_slug'] ) );
		}

		update_option( WP_Job_Manager_Post_Types::PERMALINK_OPTION_NAME, wp_json_encode( $permalink_settings ) );

		if ( function_exists( 'restore_current_locale' ) ) {
			restore_current_locale();
		}
	}
}

WP_Job_Manager_Permalink_Settings::instance();
