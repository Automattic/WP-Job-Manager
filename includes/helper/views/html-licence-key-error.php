<?php
/**
 * File containing the view for license key errors.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="error">
	<p class="wpjm-updater-dismiss" style="float:right;"><a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'dismiss-wpjm-licence-notice', $product_slug ), 'dismiss-wpjm-licence-notice', '_wpjm_nonce' ) ); ?>"><?php esc_html_e( 'Hide notice', 'wp-job-manager' ); ?></a></p>
	<p><?php printf( 'There is a problem with the license for "%s". Please <a href="%s">manage the license</a> to check for a solution and continue receiving updates.', esc_html( $plugin_data['Name'] ), esc_url( admin_url( 'edit.php?post_type=job_listing&page=job-manager-addons&section=helper#' . sanitize_title( $product_slug . '_row' ) ) ) ); ?></p>
</div>
