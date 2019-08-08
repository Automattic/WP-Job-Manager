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
 * @version     1.31.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $wp_post_types;

switch ( $job->post_status ) :
	case 'publish' :
		echo wp_kses_post(
			sprintf(
				__( '%s listed successfully. To view your listing <a href="%s">click here</a>.', 'wp-job-manager' ),
				esc_html( $wp_post_types['job_listing']->labels->singular_name ),
				get_permalink( $job->ID )
			)
		);
	break;
	case 'pending' :
		echo wp_kses_post(
			sprintf(
				esc_html__( '%s submitted successfully. Your listing will be visible once approved.', 'wp-job-manager' ),
				esc_html( $wp_post_types['job_listing']->labels->singular_name ),
				get_permalink( $job->ID )
			)
		);

		global $wpdb;

		$id = $wpdb->get_var(
			' SELECT ID
				FROM ' . $wpdb->posts . '
				WHERE
					post_type = "page"
					AND post_status = "publish"
					AND post_content LIKE "%[job_dashboard]%"'
		);

		$permalink = get_permalink($id);
		$title = get_the_title($id);

		// If job_dashboard page exists but there is no title
		if ( $permalink && empty($title) ) {
			echo wp_kses_post(
				sprintf(
					// translators: %1$s is the URL to view the listing; %2$s is
					// the plural name of the job listing post type
					__( '  <a href="%1$s"> View your %2$s</a>', 'wp-job-manager' ),
					$permalink,
					esc_html( $wp_post_types['job_listing' ]->labels->name )
				)
			);
		// If there is both a job_dashboard page and a title on the page
		} elseif ( $permalink && $title ) { 
			echo wp_kses_post(
				sprintf(
					__( '  <a href="%s"> %s</a>', 'wp-job-manager' ),
					$permalink,
					$title
				)
			);
		}
	break;
	default :
		do_action( 'job_manager_job_submitted_content_' . str_replace( '-', '_', sanitize_title( $job->post_status ) ), $job );
	break;
endswitch;

do_action( 'job_manager_job_submitted_content_after', sanitize_title( $job->post_status ), $job );
