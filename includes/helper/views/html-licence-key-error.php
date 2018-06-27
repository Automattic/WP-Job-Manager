<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<div class="error">
	<p class="wpjm-updater-dismiss" style="float:right;"><a href="<?php echo esc_url( add_query_arg( 'dismiss-wpjm-licence-notice', $product_slug ) ); ?>"><?php esc_html_e( 'Hide notice', 'wp-job-manager' ); ?></a></p>
	<p><?php printf( 'There is a problem with the license for "%s". Please <a href="%s">manage the license</a> to check for a solution and continue receiving updates.', esc_html( $plugin_data['Name'] ), esc_url( admin_url( 'edit.php?post_type=job_listing&page=job-manager-addons&section=helper#' . sanitize_title( $product_slug . '_row' ) ) ) ); ?></p>
</div>
