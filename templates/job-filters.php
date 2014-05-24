<?php wp_enqueue_script( 'wp-job-manager-ajax-filters' ); ?>
<form class="job_filters">
	<?php do_action( 'job_manager_job_filters_start', $atts ); ?>

	<div class="search_jobs">
		<?php do_action( 'job_manager_job_filters_search_jobs_start', $atts ); ?>

		<div class="search_keywords">
			<label for="search_keywords"><?php _e( 'Keywords', 'wp-job-manager' ); ?></label>
			<input type="text" name="search_keywords" id="search_keywords" placeholder="<?php _e( 'All Jobs', 'wp-job-manager' ); ?>" value="<?php echo esc_attr( $keywords ); ?>" />
		</div>

		<div class="search_location">
			<label for="search_location"><?php _e( 'Location', 'wp-job-manager' ); ?></label>
			<input type="text" name="search_location" id="search_location" placeholder="<?php _e( 'Any Location', 'wp-job-manager' ); ?>" value="<?php echo esc_attr( $location ); ?>" />
		</div>

		<?php if ( $categories ) : ?>
			<?php foreach ( $categories as $category ) : ?>
				<input type="hidden" name="search_categories[]" value="<?php echo sanitize_title( $category ); ?>" />
			<?php endforeach; ?>
		<?php elseif ( $show_categories && ! is_tax( 'job_listing_category' ) && get_terms( 'job_listing_category' ) ) : ?>
			<div class="search_categories">
				<label for="search_categories"><?php _e( 'Category', 'wp-job-manager' ); ?></label>
				<?php wp_dropdown_categories( array( 'taxonomy' => 'job_listing_category', 'hierarchical' => 1, 'show_option_all' => __( 'All Job Categories', 'wp-job-manager' ), 'name' => 'search_categories', 'orderby' => 'name', 'selected' => $selected_category ) ); ?>
			</div>
		<?php endif; ?>

		<?php do_action( 'job_manager_job_filters_search_jobs_end', $atts ); ?>
	</div>

	<?php do_action( 'job_manager_job_filters_end', $atts ); ?>
</form>