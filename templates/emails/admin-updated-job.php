<?php
/**
 * Email content when notifying admin of an updated job listing.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/emails/admin-updated-job.php.
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
?>
	<p><?php
		echo wp_kses_post(
			sprintf( __( 'A job listing has been updated on <a href="%s">%s</a>.', 'wp-job-manager' ), home_url(), esc_html( get_bloginfo( 'name' ) ) ) );
		switch ( $job->post_status ) {
			case 'publish':
				printf( ' ' . esc_html__( 'The changes have been published and are now available to the public.', 'wp-job-manager' ) );
				break;
			case 'pending':
				echo wp_kses_post( sprintf(
					' ' . __( 'The job listing is not publicly available until the changes are approved by an administrator in the site\'s <a href="%s">WordPress admin</a>.', 'wp-job-manager' ),
					esc_url( admin_url( 'edit.php?post_type=job_listing' ) )
				) );
				break;
		}
		?></p>
<?php

/**
 * Show details about the job listing.
 *
 * @param WP_Post              $job            The job listing to show details for.
 * @param WP_Job_Manager_Email $email          Email object for the notification.
 * @param bool                 $sent_to_admin  True if this is being sent to an administrator.
 * @param bool                 $plain_text     True if the email is being sent as plain text.
 */
do_action( 'job_manager_email_job_details', $job, $email, true, $plain_text );
