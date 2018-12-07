<?php
/**
 * Display the admin notice when user first activates WPJM.
 *
 * @package WP Job Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="updated wpjm-message">
	<p>
		<?php
		echo wp_kses_post( __( 'You are nearly ready to start listing jobs with <strong>WP Job Manager</strong>.', 'wp-job-manager' ) );
		?>
	</p>
	<p class="submit">
		<a href="<?php echo esc_url( admin_url( 'index.php?page=job-manager-setup' ) ); ?>" class="button-primary"><?php esc_html_e( 'Run Setup Wizard', 'wp-job-manager' ); ?></a>
		<a class="button-secondary skip" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wpjm_hide_notice', WP_Job_Manager_Admin_Notices::NOTICE_CORE_SETUP ), 'job_manager_hide_notices_nonce', '_wpjm_notice_nonce' ) ); ?>"><?php esc_html_e( 'Skip Setup', 'wp-job-manager' ); ?></a>
	</p>
</div>
