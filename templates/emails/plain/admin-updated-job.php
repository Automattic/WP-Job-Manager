<?php
/**
 * Email content when notifying admin of an updated job listing.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/emails/plain/admin-updated-job.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     WP Job Manager
 * @category    Template
 * @version     1.31.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * @var WP_Post $job
 */
$job = $args['job'];

printf( __( 'A job listing has been updated on %s (%s).', 'wp-job-manager' ), get_bloginfo( 'name' ), home_url() );
switch ( $job->post_status ) {
	case 'publish':
		printf( ' ' . __( 'The changes have been published and are now available to the public.', 'wp-job-manager' ) );
		break;
	case 'pending':
		printf( ' ' . __( 'The job listing is not publicly available until the changes are approved by an administrator in the site\'s WordPress admin (%s).', 'wp-job-manager' ), esc_url( admin_url( 'edit.php?post_type=job_listing' ) ) );
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
