<?php
/**
 * File containing the view for license key errors.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notice = [
	'level'       => 'error',
	'dismissible' => true,
	'message'     => sprintf(
		wp_kses_post(
			// translators: %1$s is the URL to the license key page, %2$s is the plugin name.
			__( 'There is a problem with the license for "%1$s". Please <a href="%2$s">manage the license</a> to check for a solution and continue receiving updates.', 'wp-job-manager' )
		),
		esc_html( $plugin_data['Name'] ),
		esc_url( admin_url( 'edit.php?post_type=job_listing&page=job-manager-addons&section=helper#' . sanitize_title( $product_slug . '_row' ) ) )
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
