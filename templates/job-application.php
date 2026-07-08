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
 * @version     1.31.1
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
