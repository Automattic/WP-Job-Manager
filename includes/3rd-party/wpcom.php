<?php
declare( strict_types=1 );

/**
 * This should be refactored to use the Docblock approach we use for the addons.
 *
 * Instead of having hardcoded the mapping here for each addon, we could define a new WPCOM product slug in the addon plugin docblock.
 *
 * This way, we won't need to update the mapping here each time we release a new addon for WPJM.
 */
$wpjm_product_to_wpcom_products = [
	'wp-job-manager-applications'          => [ '', '' ],
	'wp-job-manager-resumes'               => [ '', '' ],
	'wp-job-manager-wc-paid-listings'      => [ '', '' ],
	'wp-job-manager-tags'                  => [ '', '' ],
	'wp-job-manager-alerts'                => [ '', '' ],
	'wp-job-manager-simple-paid-listings'  => [ '', '' ],
	'wp-job-manager-application-deadline'  => [ '', '' ],
	'wp-job-manager-embeddable-job-widget' => [ '', '' ],
	'wp-job-manager-bookmarks'             => [ '', '' ],
];

/**
 * Hide the WP Job Manager activation notice for the given addon when the user purchased it from the Dotcom Marketplace.
 *
 * @param $hide_key_notice
 * @param $wpjm_product_slug
 *
 * @return bool|mixed
 */
function wpcom_hide_license_key_notice( $hide_key_notice, $wpjm_product_slug ) {
	global $wpjm_product_to_wpcom_products;

	if ( ! apply_filters( 'is_wpcom_marketplace_compatible_environment' ) ) {
		return false;
	}

	$wpcom_store_products = $wpjm_product_to_wpcom_products[ $wpjm_product_slug ];

	foreach ( $wpcom_store_products as $wpcom_store_product ) {
		if ( apply_filters( 'has_wpcom_marketplace_subscription', false, $wpcom_store_product ) ) {
			return true;
		}
	}

	return $hide_key_notice;
}

\add_filter( 'wpjm_hide_license_key_notice', 'wpcom_hide_license_key_notice', 10, 2 );

/**
 * When a user purchases the Addon from the Dotcom Marketplace, then we should hide the notices to activate it.
 *
 * @param bool $should_display_activation_link
 * @param string $wpjm_product_slug
 *
 * @return false|mixed
 */
function wpcom_hide_the_wpjm_license_for_dotcom_marketplace_products( $should_display_activation_link, $wpjm_product_slug ) {
	global $wpjm_product_to_wpcom_products;

	$wpcom_store_products = $wpjm_product_to_wpcom_products[ $wpjm_product_slug ];

	foreach ( $wpcom_store_products as $wpcom_store_product ) {
		if ( apply_filters( 'has_wpcom_marketplace_subscription', false, $wpcom_store_product ) ) {
			return false;
		}
	}

	return $should_display_activation_link;
}

\add_filter( 'wpjm_display_addon_plugin_activation_link', 'wpcom_hide_the_wpjm_license_for_dotcom_marketplace_products', 10, 2 );
\add_filter( 'wpjm_display_license_management_ui', 'wpcom_hide_the_wpjm_license_for_dotcom_marketplace_products', 10, 2 );


/**
 * Display notification on the addon when the product was purchased from the dotCom Marketplace.
 *
 * @param string $product_slug The WPJM product slug.
 *
 * @return void
 */
function wpcom_display_marketplace_license_management_information( $product_slug ) {
	if ( ! wpcom_hide_the_wpjm_license_for_dotcom_marketplace_products( true, $product_slug ) ) {
		return;
	}

	esc_html_e('This addon was purchased through WordPress.com Marketplace. You can manage the license from your WordPress.com account', 'wp-job-manager' );
}

add_action( 'wpjm_after_addon_licensing_management_ui_item', 'wpcom_display_marketplace_license_management_information', 10, 1 );


