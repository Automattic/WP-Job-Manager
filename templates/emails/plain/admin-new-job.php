<?php
/**
 * Email content when notifying admin of a new job listing.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/emails/plain/admin-new-job.php.
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

printf( esc_html__( 'A new job listing has been submitted to %s (%s).', 'wp-job-manager' ), esc_html( get_bloginfo( 'name' ) ), esc_url( home_url() ) );
switch ( $job->post_status ) {
	case 'publish':
		printf( ' ' . esc_html__( 'It has been published and is now available to the public.', 'wp-job-manager' ) );
		break;
	case 'pending':
		printf( ' ' . esc_html__( 'It is awaiting approval by an administrator in WordPress admin (%s).', 'wp-job-manager' ), esc_url( admin_url( 'edit.php?post_type=job_listing' ) ) );
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
