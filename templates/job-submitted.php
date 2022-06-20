<?php
/**
 * Notice when job has been submitted.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/job-submitted.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     wp-job-manager
 * @category    Template
 * @version     1.34.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $wp_post_types;

switch ( $job->post_status ) :
	case 'publish' :
		echo '<div class="job-manager-message">' . wp_kses_post(
			sprintf(
				// translators: %1$s is the job listing post type name, %2$s is the job listing URL.
				__( '%1$s listed successfully. To view your listing <a href="%2$s">click here</a>.', 'wp-job-manager' ),
				esc_html( $wp_post_types['job_listing']->labels->singular_name ),
				get_permalink( $job->ID )
			)
		) . '</div>';
	break;
	case 'pending' :
		echo '<div class="job-manager-message">' . wp_kses_post(
			sprintf(
				// translators: Placeholder %s is the job listing post type name.
				esc_html__( '%s submitted successfully. Your listing will be visible once approved.', 'wp-job-manager' ),
				esc_html( $wp_post_types['job_listing']->labels->singular_name )
			)
		);

		$job_dashboard_link  = job_manager_get_permalink('job_dashboard');
		$job_dashboard_title = get_the_title( job_manager_get_page_id( 'job_dashboard' ) );

		// If job_dashboard page exists but there is no title
		if ( $job_dashboard_link && empty( $job_dashboard_title ) ) {
			echo wp_kses_post(
				sprintf(
					// translators: %1$s is the URL to view the listing; %2$s is
					// the plural name of the job listing post type
					__( '  <a href="%1$s"> View your %2$s</a>', 'wp-job-manager' ),
					$job_dashboard_link,
					esc_html( $wp_post_types['job_listing' ]->labels->name )
				)
			);
		} elseif ( $job_dashboard_link && $job_dashboard_title ) { // If there is both a job_dashboard page and a title on the page
			echo wp_kses_post(
				sprintf(
					__( '  <a href="%s"> %s</a>', 'wp-job-manager' ),
					$job_dashboard_link,
					$job_dashboard_title
				)
			);
		}

		echo '</div>';
	break;
	default :
		do_action( 'job_manager_job_submitted_content_' . str_replace( '-', '_', sanitize_title( $job->post_status ) ), $job );
	break;
endswitch;

do_action( 'job_manager_job_submitted_content_after', sanitize_title( $job->post_status ), $job );
