<?php switch ( $job->post_status ) : ?>

	<?php case 'publish' : ?>

		<?php printf( __( 'Job listed successfully. To view your job listing <a href="%s">click here</a>.', 'job_manager' ), get_permalink( $job->ID ) ); ?>

	<?php break; ?>

	<?php case 'pending' : ?>

		<?php printf( __( 'Job submitted successfully. Your job listing will be visible once approved.', 'job_manager' ), get_permalink( $job->ID ) ); ?>

	<?php break; ?>

	<?php default : ?>

		<?php do_action( 'job_manager_job_submitted_content_' . str_replace( '-', '_', sanitize_title( $job->post_status ) ), $job ); ?>

<?php endswitch; ?>