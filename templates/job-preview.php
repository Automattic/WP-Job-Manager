<?php
/**
 * Job listing preview when submitting job listings.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/job-preview.php.
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
?>
<form method="post" id="job_preview" action="<?php echo esc_url( $form->get_action() ); ?>">
	<?php
	/**
	 * Fires at the top of the preview job form.
	 *
	 * @since 1.32.2
	 */
	do_action( 'preview_job_form_start' );
	?>
	<div class="job_listing_preview_title">
		<input type="submit" name="continue" id="job_preview_submit_button" class="button job-manager-button-submit-listing" value="<?php echo esc_attr( apply_filters( 'submit_job_step_preview_submit_text', __( 'Submit Listing', 'wp-job-manager' ) ) ); ?>" />
		<?php if ( ! WP_Job_Manager_Helper_Renewals::is_renew_action() ) : ?>
			<input type="submit" name="edit_job" class="button job-manager-button-edit-listing" value="<?php esc_attr_e( 'Edit listing', 'wp-job-manager' ); ?>" />
		<?php endif; ?>
		<h2><?php esc_html_e( 'Preview', 'wp-job-manager' ); ?></h2>
	</div>
	<div class="job_listing_preview single_job_listing">
		<h1><?php wpjm_the_job_title(); ?></h1>

		<?php get_job_manager_template_part( 'content-single', \WP_Job_Manager_Post_Types::PT_LISTING ); ?>

		<input type="hidden" name="job_id" value="<?php echo esc_attr( $form->get_job_id() ); ?>" />
		<input type="hidden" name="step" value="<?php echo esc_attr( $form->get_step() ); ?>" />
		<input type="hidden" name="job_manager_form" value="<?php echo esc_attr( $form->get_form_name() ); ?>" />
	</div>
	<?php
	/**
	 * Fires at the bottom of the preview job form.
	 *
	 * @since 1.32.2
	 */
	do_action( 'preview_job_form_end' );
	?>
</form>
