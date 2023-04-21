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
	'actions'     => [
		[
			'label'   => esc_html__( 'Hide notice', 'wp-job-manager' ),
			'url'     => esc_url( wp_nonce_url( add_query_arg( 'dismiss-wpjm-licence-notice', $product_slug ), 'dismiss-wpjm-licence-notice', '_wpjm_nonce' ) ),
			'primary' => false,
		],
	],
];

WP_Job_Manager_Admin_Notices::render_notice( 'wpjm_licence_notice', $notice );

