<?php
/**
 * Content for job submission (`[submit_job_form]`) shortcode.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/job-submit.php.
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

global $job_manager;
?>
<form action="<?php echo esc_url( $action ); ?>" method="post" id="submit-job-form" class="job-manager-form" enctype="multipart/form-data">

	<?php
	if ( isset( $resume_edit ) && $resume_edit ) {
		printf( '<p><strong>' . esc_html__( "You are editing an existing job. %s", 'wp-job-manager' ) . '</strong></p>', '<a href="?new=1&key=' . esc_attr( $resume_edit ) . '">' . esc_html__( 'Create A New Job', 'wp-job-manager' ) . '</a>' );
	}
	?>

	<?php 
	/**
	 * Runs before the job submission form starts.
	 *
	 * @since 1.25.0
	 */
	do_action( 'submit_job_form_start' ); 

	/**
	 * Filters the account sign in template.
	 *
	 * @since 1.15.0
	 *
	 * @param bool $val true/false
	 */
	?>
	<?php if ( apply_filters( 'submit_job_form_show_signin', true ) ) : ?>

		<?php get_job_manager_template( 'account-signin.php' ); ?>

	<?php endif; ?>

	<?php if ( job_manager_user_can_post_job() || job_manager_user_can_edit_job( $job_id ) ) : ?>

		<!-- Job Information Fields -->
		<?php 
		/**
		 * Runs before the job form fields on job submission page.
		 *
		 * @since 1.15.0
		 */
		do_action( 'submit_job_form_job_fields_start' ); ?>

		<?php foreach ( $job_fields as $key => $field ) : ?>
			<fieldset class="fieldset-<?php echo esc_attr( $key ); ?>">
				<label for="<?php echo esc_attr( $key ); ?>"><?php echo wp_kses_post( $field['label'] ) . wp_kses_post( apply_filters( 'submit_job_form_required_label', $field['required'] ? '' : ' <small>' . __( '(optional)', 'wp-job-manager' ) . '</small>', $field ) ); ?></label>
				<div class="field <?php echo $field['required'] ? 'required-field' : ''; ?>">
					<?php get_job_manager_template( 'form-fields/' . $field['type'] . '-field.php', array( 'key' => $key, 'field' => $field ) ); ?>
				</div>
			</fieldset>
		<?php endforeach; ?>

		<?php 
		/**
		 * Runs after the job form fields on job submission page.
		 *
		 * @since 1.15.0
		 */
		do_action( 'submit_job_form_job_fields_end' ); ?>

		<!-- Company Information Fields -->
		<?php if ( $company_fields ) : ?>
			<h2><?php esc_html_e( 'Company Details', 'wp-job-manager' ); ?></h2>

			<?php 
			/**
			 * Runs before the company fields start on job submission page.
			 *
			 * @since 1.15.0
			 */
			do_action( 'submit_job_form_company_fields_start' ); ?>

			<?php foreach ( $company_fields as $key => $field ) : ?>
				<fieldset class="fieldset-<?php echo esc_attr( $key ); ?>">
					<?php 
					/**
					 * Filters the required label in the company form for the given field.
					 *
					 * @since 1.15.0
					 *
					 * @param string $label The field's required label.
					 * @param string $field The field.
					 */
					?>
					<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ) . wp_kses_post( apply_filters( 'submit_job_form_required_label', $field['required'] ? '' : ' <small>' . __( '(optional)', 'wp-job-manager' ) . '</small>', $field ) ); ?></label>
					<div class="field <?php echo $field['required'] ? 'required-field' : ''; ?>">
						<?php get_job_manager_template( 'form-fields/' . $field['type'] . '-field.php', array( 'key' => $key, 'field' => $field ) ); ?>
					</div>
				</fieldset>
			<?php endforeach; ?>

			<?php 
			/**
			 * Runs after the company fields end on job submission page.
			 *
			 * @since 1.15.0
			 */
			do_action( 'submit_job_form_company_fields_end' ); ?>
		<?php endif; ?>

		<?php 
		/**
		 * Runs after the job submission form ends.
		 *
		 * @since 1.25.0
		 */
		do_action( 'submit_job_form_end' ); ?>

		<p>
			<input type="hidden" name="job_manager_form" value="<?php echo esc_attr( $form ); ?>" />
			<input type="hidden" name="job_id" value="<?php echo esc_attr( $job_id ); ?>" />
			<input type="hidden" name="step" value="<?php echo esc_attr( $step ); ?>" />
			<input type="submit" name="submit_job" class="button" value="<?php echo esc_attr( $submit_button_text ); ?>" />
			<span class="spinner" style="background-image: url(<?php echo esc_url( includes_url( 'images/spinner.gif' ) ); ?>);"></span>
		</p>

	<?php else : ?>

		<?php 
		/**
		 * Runs if the job submission form is disabled.
		 *
		 * @since 1.15.0
		 */
		do_action( 'submit_job_form_disabled' ); ?>

	<?php endif; ?>
</form>
