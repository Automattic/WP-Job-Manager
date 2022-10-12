<?php
/**
 * WP.com Marketplace licensing integration for premium core addons.
 *
 * @package wp-job-manager
 */

/**
 * Configure license configuration for WP Job Manager when purchased from WP.com Marketplace.
 *
 * @param bool|WP_Error $result The result of the licensing configuration.
 * @param array $payload        The payload receivced from WPJobManager.com back-end API.
 * @param string $event_type    The event type that triggers this filter.
 *
 * @return bool
 */
function dotcom_marketplace_configure_license_for_wp_job_manager_addon( $result, $payload, $event_type ) {
	if ( 'provision_license' !== $event_type ) {
		return $result;
	}

	$helper = WP_Job_Manager_Helper::instance();
	$helper->activate_licence( $payload['wpjm_product_slug'], $payload['license_code'], $payload['email_address'] );

	$messages = $helper->get_messages( $payload['wpjm_product_slug'] );

	$errors = array_filter(
		$messages,
		function ( $message ) {
			return 'error' === $message['type'];
		}
	);

	if ( ! empty( $errors ) ) {
		return new \WP_Error( 'error', 'An error has occurred while installing ' . $payload['wpjm_product_slug'], $errors );
	}

	return $result;
}

const WPJM_WPCOM_PRODUCTS = [
	'wp-job-manager-applications',
	'wp-job-manager-resumes',
	'wp-job-manager-simple-paid-listings',
	'wp-job-manager-wc-paid-listings',
	'wp-job-manager-tags',
	'wp-job-manager-bookmarks',
	'wp-job-manager-alerts',
	'wp-job-manager-application-deadline',
	'wp-job-manager-embeddable-job-widget',
];

foreach ( WPJM_WPCOM_PRODUCTS as $wpjm_wpcom_product ) {
	add_filter( 'wpcom_marketplace_webhook_response_' . $wpjm_wpcom_product, 'dotcom_marketplace_configure_license_for_wp_job_manager_addon', 10, 3 );
}
