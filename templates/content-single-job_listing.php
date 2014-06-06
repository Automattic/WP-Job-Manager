<?php global $job_manager; ?>

<div class="single_job_listing" itemscope itemtype="http://schema.org/JobPosting">
	<meta itemprop="title" content="<?php echo esc_attr( $post->post_title ); ?>" />

	<?php if ( $post->post_status == 'expired' ) : ?>

		<div class="job-manager-info"><?php _e( 'This job listing has expired', 'wp-job-manager' ); ?></div>

	<?php else : ?>

		<?php do_action( 'single_job_listing_start' ); ?>

		<ul class="meta">
			<?php do_action( 'single_job_listing_meta_start' ); ?>

			<li class="job-type <?php echo get_the_job_type() ? sanitize_title( get_the_job_type()->slug ) : ''; ?>" itemprop="employmentType"><?php the_job_type(); ?></li>

			<li class="location" itemprop="jobLocation"><?php the_job_location(); ?></li>

			<li class="date-posted" itemprop="datePosted"><date><?php printf( __( 'Posted %s ago', 'wp-job-manager' ), human_time_diff( get_post_time( 'U' ), current_time( 'timestamp' ) ) ); ?></date></li>

			<?php if ( is_position_filled() ) : ?>
				<li class="position-filled"><?php _e( 'This position has been filled', 'wp-job-manager' ); ?></li>
			<?php endif; ?>

			<?php do_action( 'single_job_listing_meta_end' ); ?>
		</ul>

		<?php do_action( 'single_job_listing_meta_after' ); ?>

		<div class="company" itemscope itemtype="http://data-vocabulary.org/Organization">
			<?php the_company_logo(); ?>

			<p class="name">
				<a class="website" href="<?php echo get_the_company_website(); ?>" itemprop="url" target="_blank"><?php _e( 'Website', 'wp-job-manager' ); ?></a>
				<?php the_company_twitter(); ?>
				<?php the_company_name( '<strong itemprop="name">', '</strong>' ); ?>
			</p>
			<?php the_company_tagline( '<p class="tagline">', '</p>' ); ?>
		</div>

		<div class="job_description" itemprop="description">
			<?php echo apply_filters( 'the_job_description', get_the_content() ); ?>
		</div>

		<?php if ( ! is_position_filled() && $post->post_status !== 'preview' ) get_job_manager_template( 'job-application.php' ); ?>

		<?php do_action( 'single_job_listing_end' ); ?>

	<?php endif; ?>
</div>