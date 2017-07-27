<?php
/**
 * Apply using link to website.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/job-application-url.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     WP Job Manager
 * @category    Template
 * @version     1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<p><?php _e( 'To apply for this job please visit the following URL:', 'wp-job-manager' ); ?> <a href="<?php echo esc_url( $apply->url ); ?>" target="_blank" rel="nofollow"><?php echo esc_html( $apply->url ); ?> &rarr;</a></p>