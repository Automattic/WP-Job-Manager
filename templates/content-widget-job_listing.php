<li <?php job_listing_class(); ?>>
	<a href="<?php the_job_permalink(); ?>">
		<div class="position">
			<h3><?php the_title(); ?></h3>
		</div>
		<ul class="meta">
			<li class="location"><?php the_job_location( false ); ?></li>
			<li class="company"><?php the_company_name(); ?></li>
			<?php if ( get_option( 'job_manager_enable_types' ) ) { ?>
			<li class="job-type <?php echo get_the_job_type() ? sanitize_title( get_the_job_type()->slug ) : ''; ?>"><?php the_job_type(); ?></li>
			<?php } ?>
		</ul>
	</a>
</li>
