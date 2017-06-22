<?php
/**
 * Job listing in the loop.
 *
 * @since 1.0.0
 * @version 1.26.2
 *
 * @package WP Job Manager
 * @category Template
 * @author Automattic
 */

global $post; ?>

<li <?php job_listing_class(); ?> data-longitude="<?php echo esc_attr( $post->geolocation_lat ); ?>" data-latitude="<?php echo esc_attr( $post->geolocation_long ); ?>">
	<a href="<?php the_job_permalink(); ?>">
		<?php the_company_logo(); ?>
		<div class="position">
			<h3><?php wpjm_the_job_title(); ?></h3>
			<div class="company">
				<?php the_company_name( '<strong>', '</strong> ' ); ?>
				<?php the_company_tagline( '<span class="tagline">', '</span>' ); ?>
			</div>
		</div>
		<div class="location">
			<?php the_job_location( false ); ?>
		</div>
		<ul class="meta">
			<?php do_action( 'job_listing_meta_start' ); ?>

			<?php if ( get_option( 'job_manager_enable_types' ) ) { ?>
				<?php if ( ! get_option( 'job_manager_multi_job_type', false ) ) : ?>

					<li class="job-type <?php echo get_the_job_type() ? esc_attr( sanitize_title( get_the_job_type()->slug ) ) : ''; ?>" itemprop="employmentType"><?php the_job_type(); ?></li>

				<?php else : $types = wpjm_get_the_job_types(); ?>

					<?php if ( ! empty( $types ) ) : foreach ( $types as $type ) : ?>

						<li class="job-type <?php echo esc_attr( sanitize_title( $type->slug ) ); ?>" itemprop="employmentType"><?php echo esc_html( $type->name ); ?></li>

					<?php endforeach; endif; ?>

				<?php endif; ?>
			<?php } ?>

			<li class="date"><?php the_job_publish_date(); ?></li>

			<?php do_action( 'job_listing_meta_end' ); ?>
		</ul>
	</a>
</li>
