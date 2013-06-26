<li <?php job_listing_class(); ?>>
	<a href="<?php the_job_permalink(); ?>">
		<?php the_company_logo(); ?>
		<div class="position">
			<h3><?php the_title(); ?></h3>
			<div class="company">
				<?php the_company_name( '<strong>', '</strong> ' ); ?>
				<?php the_company_tagline( '<span class="tagline">', '</span>' ); ?>
			</div>
		</div>
		<div class="location">
			<?php the_job_location( false ); ?>
		</div>
		<ul class="meta">
			<li class="job-type <?php echo get_the_job_type() ? sanitize_title( get_the_job_type()->slug ) : ''; ?>"><?php the_job_type(); ?></li>
			<li class="date"><date><?php echo human_time_diff( get_the_time( 'U' ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'job_manager' ); ?></date></li>
		</ul>
	</a>
</li>