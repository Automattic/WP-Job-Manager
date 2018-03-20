<?php
/**
 * Notice when job has been submitted.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/job-submitted.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     WP Job Manager
 * @category    Template
 * @version     1.20.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $wp_post_types;

switch ( $job->post_status ) :
	case 'publish' :
		printf( __( '%1s listed successfully. To view your listing <a href="%2s">click here</a>. Submit another job <a href="%3s">here</a>', 'wp-job-manager' ), $wp_post_types['job_listing']->labels->singular_name, get_permalink( $job->ID ), job_manager_get_permalink( 'submit_job_form' ) );
	break;
	case 'pending' :
		printf( __( '%1s submitted successfully. Your listing will be visible once approved. Submit another job <a href="%2s">here</a>', 'wp-job-manager' ), $wp_post_types['job_listing']->labels->singular_name, job_manager_get_permalink( 'submit_job_form' ) );
	break;
	default :
		do_action( 'job_manager_job_submitted_content_' . str_replace( '-', '_', sanitize_title( $job->post_status ) ), $job );
	break;
endswitch;

do_action( 'job_manager_job_submitted_content_after', sanitize_title( $job->post_status ), $job );