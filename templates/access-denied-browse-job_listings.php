<?php
/**
 * Access denied message when attempting to browse job listings.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/access-denied-browse-job_listings.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     wp-job-manager
 * @category    Template
 * @version     1.36.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p class="job-manager-error"><?php _e( 'Sorry, you do not have permission to browse job listings.', 'wp-job-manager' ); ?></p>