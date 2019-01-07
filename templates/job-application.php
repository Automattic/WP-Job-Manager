<?php
/**
 * Show job application when viewing a single job listing.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/job-application.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     WP Job Manager
 * @category    Template
 * @version     1.31.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<?php if ( $apply = get_the_job_application_method() ) :
	wp_enqueue_script( 'wp-job-manager-job-application' );
	?>
	<div class="job_application application">
		<?php 
		/**
		 * Runs before the job application method area on single job listing.
		 *
		 * @since 1.15.0
		 *
		 * @param string $apply The job application method.
		 */
		do_action( 'job_application_start', $apply ); ?>

		<input type="button" class="application_button button" value="<?php esc_attr_e( 'Apply for job', 'wp-job-manager' ); ?>" />

		<div class="application_details">
			<?php
				/**
				 * job_manager_application_details_email or job_manager_application_details_url hook
				 *
				 * @since 1.15.0
				 */
				do_action( 'job_manager_application_details_' . $apply->type, $apply );
			?>
		</div>
		<?php 
		/**
		 * Runs after the job application method area on single job listing.
		 *
		 * @since 1.15.0
		 * 
		 * @param string $apply The job application method.
		 */
		do_action( 'job_application_end', $apply ); ?>
	</div>
<?php endif; ?>
