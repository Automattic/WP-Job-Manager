<?php if ( $job->post_status == 'publish' ) : ?>

	<?php printf( __( 'Job listed successfully. To view your job listing <a href="%s">click here</a>.', 'job_manager' ), get_permalink( $job->ID ) ); ?>

<?php elseif ( $job->post_status == 'pending' ) : ?>

	<?php printf( __( 'Job submitted successfully. Your job listing will be visible once approved.', 'job_manager' ), get_permalink( $job->ID ) ); ?>

<?php endif; ?>