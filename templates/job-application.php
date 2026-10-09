<?php
/**
 * Show job application when viewing a single job listing.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/job-application.php.
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
<?php if ( $apply = get_the_job_application_method() ) :
	$is_direct_url = 'url' === $apply->type && get_option( 'job_manager_direct_apply_url' );
	if ( ! $is_direct_url ) {
		wp_enqueue_script( 'wp-job-manager-job-application' );
	}
	?>
	<div class="job_application application">
		<?php do_action( 'job_application_start', $apply ); ?>

		<?php if ( $is_direct_url ) : ?>
			<a class="application_button button" href="<?php echo esc_url( $apply->url ); ?>" rel="nofollow"><?php esc_html_e( 'Apply for job', 'wp-job-manager' ); ?></a>
			<?php
			/**
			 * Fires for the application method type, regardless of whether the intermediate
			 * details panel is rendered. Addons listing on `job_manager_application_details_url`
			 * (click tracking, redirect wrappers, etc.) still receive the event in direct-apply mode.
			 *
			 * @since $$next-version$$
			 *
			 * @param object $apply Application method object.
			 */
			do_action( 'job_manager_application_details_' . $apply->type, $apply );
			?>
		<?php else : ?>
			<input type="button" class="application_button button" value="<?php esc_attr_e( 'Apply for job', 'wp-job-manager' ); ?>" />

			<div class="application_details">
				<?php
					/**
					 * job_manager_application_details_email or job_manager_application_details_url hook
					 */
					do_action( 'job_manager_application_details_' . $apply->type, $apply );
				?>
			</div>
		<?php endif; ?>

		<?php do_action( 'job_application_end', $apply ); ?>
	</div>
<?php endif; ?>
