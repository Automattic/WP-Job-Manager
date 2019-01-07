<?php
/**
 * Setup page: header content.
 *
 * @package WP Job Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="wrap wp_job_manager wp_job_manager_addons_wrap">
	<h2><?php esc_html_e( 'WP Job Manager Setup', 'wp-job-manager' ); ?></h2>

	<ul class="wp-job-manager-setup-steps">
		<?php
		$step_classes          = array_fill( 1, 3, '' );
		$step_classes[ $step ] = 'wp-job-manager-setup-active-step';
		?>
		<li class="<?php echo sanitize_html_class( $step_classes[1] ); ?>"><?php esc_html_e( '1. Introduction', 'wp-job-manager' ); ?></li>
		<li class="<?php echo sanitize_html_class( $step_classes[2] ); ?>"><?php esc_html_e( '2. Page Setup', 'wp-job-manager' ); ?></li>
		<li class="<?php echo sanitize_html_class( $step_classes[3] ); ?>"><?php esc_html_e( '3. Done', 'wp-job-manager' ); ?></li>
	</ul>
