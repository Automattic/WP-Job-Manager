<?php if ( $apply = get_the_job_application_method() ) :
	wp_enqueue_script( 'wp-job-manager-job-application' );
	?>
	<div class="application">
		<input class="application_button" type="button" value="<?php _e( 'Apply for job', 'wp-job-manager' ); ?>" />

		<div class="application_details">
			<?php
				/**
				 * job_manager_application_details_email or job_manager_application_details_url hook
				 */
				do_action( 'job_manager_application_details_' . $apply->type, $apply );
			?>
		</div>
	</div>
<?php endif; ?>