<?php
/**
 * Job listing preview when submitting job listings.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/job-preview.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     WP Job Manager
 * @category    Template
 * @version     1.27.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<form method="post" id="job_preview" action="<?php echo esc_url( $form->get_action() ); ?>">
	<div class="job_listing_preview_title">
		<input type="submit" name="continue" id="job_preview_submit_button" class="button job-manager-button-submit-listing" value="<?php echo apply_filters( 'submit_job_step_preview_submit_text', __( 'Submit Listing', 'wp-job-manager' ) ); ?>" />
		<input type="submit" name="edit_job" class="button job-manager-button-edit-listing" value="<?php _e( 'Edit listing', 'wp-job-manager' ); ?>" />
		<h2><?php _e( 'Preview', 'wp-job-manager' ); ?></h2>
	</div>
	<div class="job_listing_preview single_job_listing">
		<h1><?php wpjm_the_job_title(); ?></h1>

		<?php get_job_manager_template_part( 'content-single', 'job_listing' ); ?>

		<input type="hidden" name="job_id" value="<?php echo esc_attr( $form->get_job_id() ); ?>" />
		<input type="hidden" name="step" value="<?php echo esc_attr( $form->get_step() ); ?>" />
		<input type="hidden" name="job_manager_form" value="<?php echo $form->get_form_name(); ?>" />
	</div>
</form>
