<li <?php job_listing_class(); ?>>
	<a href="<?php the_job_permalink(); ?>">
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
	</a>
</li>
