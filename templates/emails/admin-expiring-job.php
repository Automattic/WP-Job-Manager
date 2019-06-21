<?php
/**
 * Email content when notifying the administrator of an expiring job listing.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/emails/employer-expiring-job.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     wp-job-manager
 * @category    Template
 * @version     1.34.0
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
$edit_post_link = admin_url( sprintf( 'post.php?post=%d&amp;action=edit', $job->ID ) );

echo '<p>';
if ( $expiring_today ) {
	// translators: %1$s placeholder is URL to the blog. %2$s placeholder is the name of the site.
	echo wp_kses_post( sprintf( __( 'The following job listing is expiring today from <a href="%1$s">%2$s</a>.', 'wp-job-manager' ), esc_url( home_url() ), esc_html( get_bloginfo( 'name' ) ) ) );
} else {
	// translators: %1$s placeholder is URL to the blog. %2$s placeholder is the name of the site.
	echo wp_kses_post( sprintf( __( 'The following job listing is expiring soon from <a href="%1$s">%2$s</a>.', 'wp-job-manager' ), esc_url( home_url() ), esc_html( get_bloginfo( 'name' ) ) ) );
}

echo ' ';

// translators: Placeholder is URL to site's WP admin.
echo wp_kses_post( sprintf( __( 'Visit <a href="%s">WordPress admin</a> to manage the listing.', 'wp-job-manager' ), esc_url( $edit_post_link ) ) );
echo '</p>';

/**
 * Show details about the job listing.
 *
 * @param WP_Post              $job            The job listing to show details for.
 * @param WP_Job_Manager_Email $email          Email object for the notification.
 * @param bool                 $sent_to_admin  True if this is being sent to an administrator.
 * @param bool                 $plain_text     True if the email is being sent as plain text.
 */
do_action( 'job_manager_email_job_details', $job, $email, true, $plain_text );
