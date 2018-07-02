<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<div class="updated">
	<p class="wpjm-updater-dismiss" style="float:right;"><a href="<?php echo esc_url( add_query_arg( 'dismiss-wpjm-licence-notice', $product_slug ) ); ?>"><?php esc_html_e( 'Hide notice', 'wp-job-manager' ); ?></a></p>
	<p><?php printf( '<a href="%s">Please enter your license key</a> to get updates for "%s".', esc_url( admin_url( 'edit.php?post_type=job_listing&page=job-manager-addons&section=helper#' . sanitize_title( $product_slug . '_row' ) ) ), esc_html( $plugin_data['Name'] ) ); ?></p>
</div>
