<?php
/**
 * File containing the view for license key notices.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notice = [
	'level'       => 'info',
	'dismissible' => true,
	'message'     => sprintf(
		wp_kses_post(
			// translators: %1$s is the URL to the license key page, %2$s is the plugin name.
			__( '<a href="%1$s">Please enter your license key</a> to get updates for "%2$s".', 'wp-job-manager' )
		),
		esc_url( admin_url( 'edit.php?post_type=job_listing&page=job-manager-addons&section=helper#' . sanitize_title( $product_slug . '_row' ) ) ),
		esc_html( $plugin_data['Name'] )
	),
];

WP_Job_Manager_Admin_Notices::render_notice( 'wpjm_licence_notice', $notice );

