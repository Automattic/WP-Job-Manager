<?php
/**
 * File containing the class WP_Job_Manager_Dependency_Checker.
 *
 * @package wp-job-manager
 * @since   1.33.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles checking for WP Job Manager's dependencies.
 *
 * @since 1.33.0
 */
class WP_Job_Manager_Dependency_Checker {
	const MINIMUM_PHP_VERSION = '5.6.20';
	const MINIMUM_WP_VERSION  = '4.9.0';

	/**
	 * Check if WP Job Manager's dependencies have been met.
	 *
	 * @return bool True if we should continue to load the plugin.
	 */
	public static function check_dependencies() {
		if ( ! self::check_php() ) {
			add_action( 'admin_notices', [ 'WP_Job_Manager_Dependency_Checker', 'add_php_notice' ] );
			add_action( 'admin_init', [ __CLASS__, 'deactivate_self' ] );

			return false;
		}

		if ( ! self::check_wp() ) {
			add_action( 'admin_notices', [ 'WP_Job_Manager_Dependency_Checker', 'add_wp_notice' ] );
			add_filter( 'plugin_action_links_' . JOB_MANAGER_PLUGIN_BASENAME, [ 'WP_Job_Manager_Dependency_Checker', 'wp_version_plugin_action_notice' ] );
		}

		return true;
	}

	/**
	 * Checks for our PHP version requirement.
	 *
	 * @return bool
	 */
	private static function check_php() {
		return version_compare( phpversion(), self::MINIMUM_PHP_VERSION, '>=' );
	}

	/**
	 * Adds notice in WP Admin that minimum version of PHP is not met.
	 *
	 * @access private
	 */
	public static function add_php_notice() {
		$screen        = get_current_screen();
		$valid_screens = self::get_critical_screen_ids();

		if ( null === $screen || ! current_user_can( 'activate_plugins' ) || ! in_array( $screen->id, $valid_screens, true ) ) {
			return;
		}

		// translators: %1$s is version of PHP that WP Job Manager requires; %2$s is the version of PHP WordPress is running on.
		$message = sprintf( __( '<strong>WP Job Manager</strong> requires a minimum PHP version of %1$s, but you are running %2$s.', 'wp-job-manager' ), self::MINIMUM_PHP_VERSION, phpversion() );

		echo '<div class="error"><p>';
		echo wp_kses( $message, [ 'strong' => [] ] );
		$php_update_url = 'https://wordpress.org/support/update-php/';
		if ( function_exists( 'wp_get_update_php_url' ) ) {
			$php_update_url = wp_get_update_php_url();
		}
		printf(
			'<p><a class="button button-primary" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s <span class="screen-reader-text">%3$s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a></p>',
			esc_url( $php_update_url ),
			esc_html__( 'Learn more about updating PHP', 'wp-job-manager' ),
			/* translators: accessibility text */
			esc_html__( '(opens in a new tab)', 'wp-job-manager' )
		);
		echo '</p></div>';
	}

	/**
	 * Deactivate self.
	 */
	public static function deactivate_self() {
		deactivate_plugins( JOB_MANAGER_PLUGIN_BASENAME );
	}

	/**
	 * Checks for our WordPress version requirement.
	 *
	 * @return bool
	 */
	private static function check_wp() {
		global $wp_version;

		return version_compare( $wp_version, self::MINIMUM_WP_VERSION, '>=' );
	}

	/**
	 * Adds notice in WP Admin that minimum version of WordPress is not met.
	 *
	 * @access private
	 */
	public static function add_wp_notice() {
		$screen        = get_current_screen();
		$valid_screens = self::get_critical_screen_ids();

		if ( null === $screen || ! in_array( $screen->id, $valid_screens, true ) ) {
			return;
		}

		$update_action_link = '';
		if ( current_user_can( 'update_core' ) ) {
			// translators: %s is the URL for the page where users can go to update WordPress.
			$update_action_link = ' ' . sprintf( __( 'Please <a href="%s">update WordPress</a> to avoid issues.', 'wp-job-manager' ), esc_url( self_admin_url( 'update-core.php' ) ) );
		}

		echo '<div class="error">';
		echo '<p>' . wp_kses_post( __( '<strong>WP Job Manager</strong> requires a more recent version of WordPress.', 'wp-job-manager' ) . $update_action_link ) . '</p>';
		echo '</div>';
	}

	/**
	 * Add admin notice when WP upgrade is required.
	 *
	 * @access private
	 *
	 * @param array $actions Actions to show in WordPress admin's plugin list.
	 * @return array
	 */
	public static function wp_version_plugin_action_notice( $actions ) {
		if ( ! current_user_can( 'update_core' ) ) {
			$actions[] = '<strong style="color: red">' . esc_html__( 'WordPress Update Required', 'wp-job-manager' ) . '</strong>';
		} else {
			$actions[] = '<a href="' . esc_url( self_admin_url( 'update-core.php' ) ) . '" style="color: red">' . esc_html__( 'WordPress Update Required', 'wp-job-manager' ) . '</a>';
		}
		return $actions;
	}

	/**
	 * Returns the screen IDs where dependency notices should be displayed.
	 *
	 * @return array
	 */
	private static function get_critical_screen_ids() {
		return [ 'dashboard', 'plugins', 'plugins-network', 'edit-job_listing', 'job_listing_page_job-manager-settings' ];
	}
}
