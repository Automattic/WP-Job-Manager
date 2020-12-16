<?php
/**
 * Email content when notifying the administrator of an expiring job listing.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/emails/plain/employer-expiring-job.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     wp-job-manager
 * @category    Template
 * @version     1.31.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * @var WP_Post $job
 */
$job = $args['job'];

/**
 * @var bool
 */
$expiring_today = $args['expiring_today'];

if ( $expiring_today ) {
	printf(
		// translators: %1$s placeholder is the name of the site, %2$s placeholder is URL to the blog.
		esc_html__( 'The following job listing is expiring today from %1$s (%2$s).', 'wp-job-manager' ),
		esc_html( get_bloginfo( 'name' ) ),
		esc_url( home_url() )
	);
} else {
	printf(
		// translators: %1$s placeholder is the name of the site, %2$s placeholder is URL to the blog.
		esc_html__( 'The following job listing is expiring soon from %1$s (%2$s).', 'wp-job-manager' ),
		esc_html( get_bloginfo( 'name' ) ),
		esc_url( home_url() )
	);
}
$edit_post_link = admin_url( sprintf( 'post.php?post=%d&amp;action=edit', $job->ID ) );
printf(
	// translators: Placeholder %s is the edit job URL.
	' ' . esc_html__( 'Visit WordPress admin (%s) to manage the listing.', 'wp-job-manager' ),
	esc_url( $edit_post_link )
);

/**
 * Show details about the job listing.
 *
 * @param WP_Post              $job            The job listing to show details for.
 * @param WP_Job_Manager_Email $email          Email object for the notification.
 * @param bool                 $sent_to_admin  True if this is being sent to an administrator.
 * @param bool                 $plain_text     True if the email is being sent as plain text.
 */
do_action( 'job_manager_email_job_details', $job, $email, true, $plain_text );
