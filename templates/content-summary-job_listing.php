<?php global $job_manager; ?>

<a href="<?php the_permalink(); ?>">
	<div class="job-type <?php echo get_the_job_type() ? sanitize_title( get_the_job_type()->slug ) : ''; ?>"><?php the_job_type(); ?></div>

	<?php if ( $logo = get_the_company_logo() ) : ?>
		<img src="<?php echo esc_attr( $logo ); ?>" alt="<?php the_company_name(); ?>" title="<?php the_company_name(); ?> - <?php the_company_tagline(); ?>" />
	<?php endif; ?>

	<div class="job_summary_content">

		<h1><?php the_title(); ?></h1>

		<p class="meta"><?php the_job_location( false ); ?> &mdash; <date><?php printf( __( 'Posted %s ago', 'wp-job-manager' ), human_time_diff( get_post_time( 'U' ), current_time( 'timestamp' ) ) ); ?></date></p>

	</div>
</a>
