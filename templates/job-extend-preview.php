<?php
/**
 * Job listing preview when extending job listings.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/job-extend-preview.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     wp-job-manager
 * @category    Template
 * @version     $$next-version$$
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<form method="post" id="job_preview" action="<?php echo esc_url( $form->get_action() ); ?>">
	<?php
	/**
	 * Fires at the top of the extend job listing.
	 *
	 * @since $$next-version$$
	 */
	do_action( 'preview_job_form_start' );
	?>
	<div class="job_listing_preview_title">
		<input type="submit" name="continue" id="job_preview_submit_button" class="button job-manager-button-submit-listing" value="<?php echo esc_attr( __( 'Extend Listing', 'wp-job-manager' ) ); ?>" />
		<h2><?php esc_html_e( 'Extend Listing Expiration', 'wp-job-manager' ); ?></h2>
	</div>
	<div class="job_listing_preview single_job_listing">
		<h1><?php wpjm_the_job_title(); ?></h1>

		<?php get_job_manager_template_part( 'content-single', 'job_listing' ); ?>

		<input type="hidden" name="job_id" value="<?php echo esc_attr( $form->get_job_id() ); ?>" />
		<input type="hidden" name="step" value="<?php echo esc_attr( $form->get_step() ); ?>" />
		<input type="hidden" name="job_manager_form" value="<?php echo esc_attr( $form->get_form_name() ); ?>" />
	</div>
	<?php
	/**
	 * Fires at the bottom of the extend job listing.
	 *
	 * @since $$next-version$$
	 */
	do_action( 'preview_job_form_end' );
	?>
</form>
