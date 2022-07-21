<?php
/**
 * Message to display when access is denied to a single job listing.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/access-denied-single-job_listing.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     wp-job-manager
 * @category    Template
 * @since       1.37.0
 * @version     1.37.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( $post->post_status === 'expired' ) : ?>
	<div class="job-manager-info"><?php _e( 'This listing has expired', 'wp-job-manager' ); ?></div>
<?php else : ?>
	<p class="job-manager-error"><?php _e( 'Sorry, you do not have permission to view this job listing.', 'wp-job-manager' ); ?></p>
<?php endif; ?>
