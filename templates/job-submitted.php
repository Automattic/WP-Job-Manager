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
 * @version     1.41.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $wp_post_types;

/**
 * Triggers before the job-submitted template is displayed.
 *
 * @since 1.41.0
 *
 * @param WP_Post $job The job that was submitted.
 */
do_action( 'job_manager_job_submitted_content_before', $job );

$job = get_post( $job->ID );

switch ( $job->post_status ) :
	case 'publish' :
		$job_submitted_content = '<div class="job-manager-message">' . wp_kses_post(
			sprintf(
				// translators: %1$s is the job listing post type name, %2$s is the job listing URL.
				__( '%1$s listed successfully. To view your listing <a href="%2$s">click here</a>.', 'wp-job-manager' ),
				esc_html( $wp_post_types['job_listing']->labels->singular_name ),
				get_permalink( $job->ID )
			)
		) . '</div>';

		break;
	case 'pending' :
		$job_submitted_content = '<div class="job-manager-message">' . wp_kses_post(
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
			$job_submitted_content .= wp_kses_post(
				sprintf(
					// translators: %1$s is the URL to view the listing; %2$s is
					// the plural name of the job listing post type
					__( '  <a href="%1$s"> View your %2$s</a>', 'wp-job-manager' ),
					$job_dashboard_link,
					esc_html( $wp_post_types['job_listing' ]->labels->name )
				)
			);
		} elseif ( $job_dashboard_link && $job_dashboard_title ) { // If there is both a job_dashboard page and a title on the page
			$job_submitted_content .= wp_kses_post(
				sprintf(
					__( '  <a href="%s"> %s</a>', 'wp-job-manager' ),
					$job_dashboard_link,
					$job_dashboard_title
				)
			);
		}

		$job_submitted_content .= '</div>';
	break;
	default :
		// Backwards compatibility for installations which used this action.
		ob_start();
		do_action( 'job_manager_job_submitted_content_' . str_replace( '-', '_', sanitize_title( $job->post_status ) ), $job );
		$content = ob_get_clean();

		if ( ! empty( $content ) ) {
			$job_submitted_content = $content;
			break;
		}

		$job_submitted_content = '<div class="job-manager-message">' . wp_kses_post(
			sprintf(
			// translators: %1$s is the job listing post type name.
				__( '%1$s submitted successfully.', 'wp-job-manager' ),
				esc_html( $wp_post_types['job_listing']->labels->singular_name )
			)
		) . '</div>';

	break;
endswitch;

/**
 * Filters the job submitted contents.
 *
 * @since 1.41.0
 *
 * @param string $job_submitted_content The content to filter.
 * @param WP_Post $job The job that was submitted.
 */
$job_submitted_content = apply_filters( 'job_manager_job_submitted_content', $job_submitted_content, $job );

echo $job_submitted_content;

do_action( 'job_manager_job_submitted_content_after', sanitize_title( $job->post_status ), $job );
