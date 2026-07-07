<?php
/**
 * Single job listing.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/content-single-job_listing.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     wp-job-manager
 * @category    Template
 * @since       1.0.0
 * @version     1.37.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $post;

// Defense in depth: the normal single-listing render is gated in WP_Job_Manager_Post_Types::job_content(),
// but this template is also loaded directly by the [job] shortcode, which bypasses that guard. Re-assert the
// post-password check here (with the same super-admin exception) so a shortcode-embedded protected listing
// cannot leak its content. See templates/content-job_listing.php for the sibling pattern.
if ( ( ! post_password_required( $post ) || is_super_admin() ) && job_manager_user_can_view_job_listing( $post->ID ) ) : ?>
	<div class="single_job_listing">
		<?php if ( get_option( 'job_manager_hide_expired_content', 1 ) && 'expired' === $post->post_status ) : ?>
			<div class="job-manager-info"><?php _e( 'This listing has expired.', 'wp-job-manager' ); ?></div>
		<?php else : ?>
			<?php
				/**
				 * single_job_listing_start hook
				 *
				 * @hooked job_listing_meta_display - 20
				 * @hooked job_listing_company_display - 30
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
				 * single_job_listing_end hook
				 */
				do_action( 'single_job_listing_end' );
			?>
		<?php endif; ?>
	</div>
<?php else : ?>

	<?php get_job_manager_template_part( 'access-denied', 'single-job_listing' ); ?>

<?php endif; ?>
