<div id="job-manager-job-dashboard">
	<p><?php _e( 'Your job listings are shown in the table below. Expired listings will be automatically removed after 30 days.', 'wp-job-manager' ); ?></p>
	<table class="job-manager-jobs">
		<thead>
			<tr>
				<th class="job_title"><?php _e( 'Job Title', 'wp-job-manager' ); ?></th>
				<th class="date"><?php _e( 'Date Posted', 'wp-job-manager' ); ?></th>
				<th class="status"><?php _e( 'Status', 'wp-job-manager' ); ?></th>
				<th class="expires"><?php _e( 'Expires', 'wp-job-manager' ); ?></th>
				<th class="filled"><?php _e( 'Filled?', 'wp-job-manager' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! $jobs ) : ?>
				<tr>
					<td colspan="6"><?php _e( 'You do not have any active job listings.', 'wp-job-manager' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $jobs as $job ) : ?>
					<tr>
						<td class="job_title">
							<?php if ( $job->post_status == 'publish' ) : ?>
								<a href="<?php echo get_permalink( $job->ID ); ?>"><?php echo $job->post_title; ?></a>
							<?php else : ?>
								<?php echo $job->post_title; ?>
							<?php endif; ?>
							<ul class="job-dashboard-actions">
								<?php
									$actions = array();

									switch ( $job->post_status ) {
										case 'publish' :
											$actions['edit'] = array( 'label' => __( 'Edit', 'wp-job-manager' ), 'nonce' => false );

											if ( is_position_filled( $job ) )
												$actions['mark_not_filled'] = array( 'label' => __( 'Mark not filled', 'wp-job-manager' ), 'nonce' => true );
											else
												$actions['mark_filled'] = array( 'label' => __( 'Mark filled', 'wp-job-manager' ), 'nonce' => true );

											break;
									}

									$actions['delete'] = array( 'label' => __( 'Delete', 'wp-job-manager' ), 'nonce' => true );
									$actions           = apply_filters( 'job_manager_my_job_actions', $actions, $job );

									foreach ( $actions as $action => $value ) {
										$action_url = add_query_arg( array( 'action' => $action, 'job_id' => $job->ID ) );
										if ( $value['nonce'] )
											$action_url = wp_nonce_url( $action_url, 'job_manager_my_job_actions' );
										echo '<li><a href="' . $action_url . '" class="job-dashboard-action-' . $action . '">' . $value['label'] . '</a></li>';
									}
								?>
							</ul>
						</td>
						<td class="date"><?php echo date_i18n( get_option( 'date_format' ), strtotime( $job->post_date ) ); ?></td>
						<td class="status"><?php the_job_status( $job ); ?></td>
						<td class="expires"><?php
							$expires = $job->_job_expires;

							echo $expires ? date_i18n( get_option( 'date_format' ), strtotime( $expires ) ) : '&ndash;';
						?></td>
						<td class="filled"><?php if ( is_position_filled( $job ) ) echo '&#10004;'; else echo '&ndash;'; ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
	<?php get_job_manager_template( 'pagination.php', array( 'max_num_pages' => $max_num_pages ) ); ?>
</div>