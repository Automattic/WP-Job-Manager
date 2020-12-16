<?php
/**
 * File containing the view for license key notices.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="updated">
	<p class="wpjm-updater-dismiss" style="float:right;"><a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'dismiss-wpjm-licence-notice', $product_slug ), 'dismiss-wpjm-licence-notice', '_wpjm_nonce' ) ); ?>"><?php esc_html_e( 'Hide notice', 'wp-job-manager' ); ?></a></p>
	<?php // translators: %1$s is the license setting page URL, %2$s is the plugin name. ?>
	<p><?php printf( wp_kses_post( __( '<a href="%1$s">Please enter your license key</a> to get updates for "%2$s".', 'wp-job-manager' ) ), esc_url( admin_url( 'edit.php?post_type=job_listing&page=job-manager-addons&section=helper#' . sanitize_title( $product_slug . '_row' ) ) ), esc_html( $plugin_data['Name'] ) ); ?></p>
</div>
