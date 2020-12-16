<?php
/**
 * Single job listing widget content.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/content-widget-job_listing.php.
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
<li <?php job_listing_class(); ?>>
	<a href="<?php the_job_permalink(); ?>">
		<?php if ( isset( $show_logo ) && $show_logo ) { ?>
		<div class="image">
			<?php the_company_logo(); ?>
		</div>
		<?php } ?>
		<div class="content">
			<div class="position">
				<h3><?php wpjm_the_job_title(); ?></h3>
			</div>
			<ul class="meta">
				<li class="location"><?php the_job_location( false ); ?></li>
				<li class="company"><?php the_company_name(); ?></li>
				<?php if ( get_option( 'job_manager_enable_types' ) ) { ?>
					<?php $types = wpjm_get_the_job_types(); ?>
					<?php if ( ! empty( $types ) ) : foreach ( $types as $type ) : ?>
						<li class="job-type <?php echo esc_attr( sanitize_title( $type->slug ) ); ?>"><?php echo esc_html( $type->name ); ?></li>
					<?php endforeach; endif; ?>
				<?php } ?>
			</ul>
		</div>
	</a>
</li>
