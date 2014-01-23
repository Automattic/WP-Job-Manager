<?php
switch ( $job->post_status ) :
	case 'publish' :
		printf( __( 'Job listed successfully. To view your job listing <a href="%s">click here</a>.', 'wp-job-manager' ), get_permalink( $job->ID ) );
	break;
	case 'pending' :
		printf( __( 'Job submitted successfully. Your job listing will be visible once approved.', 'wp-job-manager' ), get_permalink( $job->ID ) );
	break;
	default :
		do_action( 'job_manager_job_submitted_content_' . str_replace( '-', '_', sanitize_title( $job->post_status ) ), $job );
	break;
endswitch;