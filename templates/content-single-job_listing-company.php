<?php
/**
 * Single view Company information box
 *
 * Hooked into single_job_listing_start priority 30
 *
 * @since  1.14.0
 */
?>
<div class="company" itemscope itemtype="http://data-vocabulary.org/Organization">
	<?php the_company_logo(); ?>

	<p class="name">
		<a class="website" href="<?php echo get_the_company_website(); ?>" itemprop="url" target="_blank"><?php _e( 'Website', 'wp-job-manager' ); ?></a>
		<?php the_company_twitter(); ?>
		<?php the_company_name( '<strong itemprop="name">', '</strong>' ); ?>
	</p>
	<?php the_company_tagline( '<p class="tagline">', '</p>' ); ?>
	<?php the_company_video(); ?>
</div>