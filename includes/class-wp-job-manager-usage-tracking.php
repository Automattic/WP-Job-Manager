<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include dirname( __FILE__ ) . '/../lib/usage-tracking/class-usage-tracking-base.php';

/**
 * WPJM Usage Tracking subclass.
 **/
class WP_Job_Manager_Usage_Tracking extends WP_Job_Manager_Usage_Tracking_Base {

	const WPJM_SETTING_NAME = 'job_manager_usage_tracking_enabled';

	const WPJM_TRACKING_INFO_URL = 'https://wpjobmanager.com/document/what-data-does-wpjm-track';

	protected function __construct() {
		parent::__construct();

		// Add filter for settings.
		add_filter( 'job_manager_settings', array( $this, 'add_setting_field' ) );

		// In the setup wizard, do not display the normal opt-in dialog.
		if ( isset( $_GET['page'] ) && 'job-manager-setup' == $_GET['page'] ) {
			remove_action( 'admin_notices', array( $this, 'maybe_display_tracking_opt_in' ) );
		}
	}

	/*
	 * Implementation for abstract functions.
	 */

	public static function get_instance() {
		return self::get_instance_for_subclass( get_class() );
	}

	protected function get_prefix() {
		return 'wpjm';
	}

	protected function get_text_domain() {
		return 'wp-job-manager';
	}

	protected function get_tracking_enabled() {
		return get_option( self::WPJM_SETTING_NAME  ) || false;
	}

	protected function set_tracking_enabled( $enable ) {
		update_option( self::WPJM_SETTING_NAME, $enable );
	}

	protected function current_user_can_manage_tracking() {
		return current_user_can( 'manage_options' );
	}

	protected function opt_in_dialog_text() {
		return sprintf( __( "We'd love if you helped us make WP Job Manager better by allowing us to collect
			<a href=\"%s\" target=\"_blank\">usage tracking data</a>.
			No sensitive information is collected, and you can opt out at any time.",
			'wp-job-manager' ), self::WPJM_TRACKING_INFO_URL );
	}


	/**
	 * If needed, display opt-in dialog for the setup wizard. This is
	 * designed to use the same JavaScript code as the usual opt-in dialog.
	 *
	 * @param string $html the HTML code to display if opt-in is not needed,
	 * or after opt-in is completed.
	 */
	public function maybe_display_tracking_opt_in_for_wizard( $html ) {
		$opt_in_hidden         = $this->is_opt_in_hidden();
		$user_tracking_enabled = $this->is_tracking_enabled();
		$can_manage_tracking   = $this->current_user_can_manage_tracking();

		if ( ! $user_tracking_enabled && ! $opt_in_hidden && $can_manage_tracking ) { ?>
			<div id="<?php echo esc_attr( $this->get_prefix() ); ?>-usage-tracking-notice"
				data-nonce="<?php echo esc_attr( wp_create_nonce( 'tracking-opt-in' ) ); ?>">
				<p>
					<?php echo wp_kses( $this->opt_in_dialog_text(), $this->opt_in_dialog_text_allowed_html() ); ?>
				</p>
				<p>
					<button class="button button-primary" data-enable-tracking="yes">
						<?php esc_html_e( 'Enable Usage Tracking', 'a8c-usage-tracking' ); ?>
					</button>
					<button class="button" data-enable-tracking="no">
						<?php esc_html_e( 'Disable Usage Tracking', 'a8c-usage-tracking' ); ?>
					</button>
					<span id="progress" class="spinner alignleft"></span>
				</p>
			</div>
			<div id="<?php echo esc_attr( $this->get_prefix() ); ?>-usage-tracking-enable-success" class="hidden">
				<p><?php esc_html_e( 'Usage data enabled. Thank you!', 'a8c-usage-tracking' ); ?></p>
				<?php echo $html;?>
			</div>
			<div id="<?php echo esc_attr( $this->get_prefix() ); ?>-usage-tracking-disable-success" class="hidden">
				<p><?php esc_html_e( 'Disabled usage tracking.', 'a8c-usage-tracking' ); ?></p>
				<?php echo $html;?>
			</div>
			<div id="<?php echo esc_attr( $this->get_prefix() ); ?>-usage-tracking-failure" class="hidden">
				<p><?php esc_html_e( 'Something went wrong. Please try again later.', 'a8c-usage-tracking' ); ?></p>
				<?php echo $html;?>
			</div>
		<?php
		} else {
			echo $html;
		}
	}


	/*
	 * Hooks.
	 */

	public function add_setting_field( $fields ) {
		$fields['general'][1][] = array(
			'name'     => self::WPJM_SETTING_NAME,
			'std'      => '0',
			'type'     => 'checkbox',
			'desc'     => '',
			'label'    => __( 'Enable usage tracking', 'wp-job-manager' ),
			'cb_label' => sprintf(
				__(
					'Help us make WP Job Manager better by allowing us to collect
					<a href="%s" target="_blank">usage tracking data</a>.
					No sensitive information is collected.', 'wp-job-manager'
				), self::WPJM_TRACKING_INFO_URL
			),
		);

		return $fields;
	}


	/*
	 * Helpers.
	 */

	public function clear_options() {
		delete_option( self::WPJM_SETTING_NAME );
		delete_option( $this->hide_tracking_opt_in_option_name );
	}
}
