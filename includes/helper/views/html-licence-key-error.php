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
	<?php // translators: %1$s is the plugin name, %2$s is the license setting page URL. ?>
	<p><?php printf( wp_kses_post( __( 'There is a problem with the license for "%1$s". Please <a href="%2$s">manage the license</a> to check for a solution and continue receiving updates.', 'wp-job-manager' ) ), esc_html( $plugin_data['Name'] ), esc_url( admin_url( 'edit.php?post_type=job_listing&page=job-manager-addons&section=helper#' . sanitize_title( $product_slug . '_row' ) ) ) ); ?></p>
</div>
