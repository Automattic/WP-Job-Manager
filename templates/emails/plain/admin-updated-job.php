<?php
/**
 * Email content when notifying admin of an updated job listing.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/emails/plain/admin-updated-job.php.
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

// translators: %1$s placeholder is the name of the site, %2$s placeholder is URL to the blog.
printf( esc_html__( 'A job listing has been updated on %1$s (%2$s).', 'wp-job-manager' ), esc_html( get_bloginfo( 'name' ) ), esc_url( home_url() ) );
switch ( $job->post_status ) {
	case 'publish':
		printf( ' ' . esc_html__( 'The changes have been published and are now available to the public.', 'wp-job-manager' ) );
		break;
	case 'pending':
		// translators: Placeholder %s is the admin job listings URL.
		printf( ' ' . esc_html__( 'The job listing is not publicly available until the changes are approved by an administrator in the site\'s WordPress admin (%s).', 'wp-job-manager' ), esc_url( admin_url( 'edit.php?post_type=job_listing' ) ) );
		break;
}

/**
 * Show details about the job listing.
 *
 * @param WP_Post              $job            The job listing to show details for.
 * @param WP_Job_Manager_Email $email          Email object for the notification.
 * @param bool                 $sent_to_admin  True if this is being sent to an administrator.
 * @param bool                 $plain_text     True if the email is being sent as plain text.
 */
do_action( 'job_manager_email_job_details', $job, $email, true, $plain_text );
