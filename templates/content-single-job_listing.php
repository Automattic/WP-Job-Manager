<?php
/**
 * Single job listing.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/content-single-job_listing.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     WP Job Manager
 * @category    Template
 * @since       1.0.0
 * @version     1.28.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $post;
?>
<div class="single_job_listing">
	<?php if ( get_option( 'job_manager_hide_expired_content', 1 ) && 'expired' === $post->post_status ) : ?>
		<div class="job-manager-info"><?php _e( 'This listing has expired.', 'wp-job-manager' ); ?></div>
	<?php else : ?>
		<?php
			/**
			 * Runs before the job description of single job listing.
			 *
			 * @hooked job_listing_meta_display - 20
			 * @hooked job_listing_company_display - 30
			 *
			 * @since 1.21.0
			 */
			do_action( 'single_job_listing_start' );
		?>

		<div class="job_description">
			<?php wpjm_the_job_description(); ?>
		</div>

		<?php if ( candidates_can_apply() ) : ?>
			<?php get_job_manager_template( 'job-application.php' ); ?>
		<?php endif; ?>

		<?php
			/**
			 * Runs after the end of single job listing.
			 * 
			 * @since 1.21.0
			 */
			do_action( 'single_job_listing_end' );
		?>
	<?php endif; ?>
</div>
