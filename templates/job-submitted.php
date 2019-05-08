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
	break;
	default :
		do_action( 'job_manager_job_submitted_content_' . str_replace( '-', '_', sanitize_title( $job->post_status ) ), $job );
	break;
endswitch;

do_action( 'job_manager_job_submitted_content_after', sanitize_title( $job->post_status ), $job );
