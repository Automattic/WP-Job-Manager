<?php
/**
 * Email content when notifying employers of an expiring job listing.
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
	// translators: %1$s placeholder is the name of the site, %2$s placeholder is URL to the blog.
	printf( esc_html__( 'The following job listing is expiring today from %1$s (%2$s).', 'wp-job-manager' ), esc_html( get_bloginfo( 'name' ) ), esc_url( home_url() ) );
} else {
	// translators: %1$s placeholder is the name of the site, %2$s placeholder is URL to the blog.
	printf( esc_html__( 'The following job listing is expiring soon from %1$s (%2$s).', 'wp-job-manager' ), esc_html( get_bloginfo( 'name' ) ), esc_url( home_url() ) );
}
// translators: Placeholder %s is the job listing dashboard URL.
printf( ' ' . esc_html__( 'Visit the job listing dashboard (%s) to manage the listing.', 'wp-job-manager' ), esc_url( job_manager_get_permalink( 'job_dashboard' ) ) );

/**
 * Show details about the job listing.
 *
 * @param WP_Post              $job            The job listing to show details for.
 * @param WP_Job_Manager_Email $email          Email object for the notification.
 * @param bool                 $sent_to_admin  True if this is being sent to an administrator.
 * @param bool                 $plain_text     True if the email is being sent as plain text.
 */
do_action( 'job_manager_email_job_details', $job, $email, false, $plain_text );
