<?php
/**
 * Job dashboard shortcode content.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/job-dashboard.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     wp-job-manager
 * @category    Template
 * @version     1.41.0
 *
 * @since 1.34.4 Available job actions are passed in an array (`$job_actions`, keyed by job ID) and not generated in the template.
 * @since 1.35.0 Switched to new date functions.
 *
 * @var array     $job_dashboard_columns Array of the columns to show on the job dashboard page.
 * @var int       $max_num_pages Maximum number of pages
 * @var WP_Post[] $jobs Array of job post results.
 * @var array     $job_actions Array of actions available for each job.
 */

use WP_Job_Manager\UI\UI_Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$submit_job_form_page_id = get_option( 'job_manager_submit_job_form_page_id' );
?>

<div id="job-manager-job-dashboard" class="alignwide">
	<div class="jm-dashboard__intro">
		<p><?php esc_html_e( 'Your listings are shown in the table below.', 'wp-job-manager' ); ?></p>
		<?php if ( job_manager_user_can_submit_job_listing() ) : ?>
			<div>
				<a class="wp-element-button button"
					href="<?php echo esc_url( get_permalink( $submit_job_form_page_id ) ); ?>"><?php esc_html_e( 'Add Job', 'wp-job-manager' ); ?></a>
			</div>
		<?php endif; ?>
	</div>
	<div class="job-manager-jobs jm-dashboard">
		<div class="jm-dashboard-header">
			<?php foreach ( $job_dashboard_columns as $key => $column ) : ?>
				<div
					class="jm-dashboard-job-column <?php echo esc_attr( $key ); ?>"><?php echo esc_html( $column ); ?></div>
			<?php endforeach; ?>
			<div class="jm-dashboard-job-column actions"><?php esc_html_e( 'Actions', 'wp-job-manager' ); ?></div>
		</div>
		<div class="jm-dashboard-rows">
			<?php if ( ! $jobs ) : ?>
				<div
					class="jm-dashboard-empty"><?php esc_html_e( 'You do not have any active listings.', 'wp-job-manager' ); ?></div>
			<?php else : ?>
				<?php foreach ( $jobs as $job ) : ?>
					<div class="jm-dashboard-job">
						<?php foreach ( $job_dashboard_columns as $key => $column ) : ?>
							<div class="jm-dashboard-job-column <?php echo esc_attr( $key ); ?>"
								aria-label="<?php echo esc_attr( $column ); ?>">
								<?php

								switch ( $key ) {
									case 'job_title':
										echo '<a class="job-title" href="' . esc_url( get_permalink( $job->ID ) ) . '">' . ( wpjm_get_the_job_title( $job ) ?? $job->ID ) . '</a>';
										break;
									case 'date':
										echo '<div>' . esc_html( wp_date( apply_filters( 'job_manager_get_dashboard_date_format', 'M d, Y' ), get_post_datetime( $job )->getTimestamp() ) ) . '</div>';

										break;
								}

								do_action( 'job_manager_job_dashboard_column_' . $key, $job );

								?>
							</div>
						<?php endforeach; ?>
						<div class="jm-dashboard-job-column actions job-dashboard-job-actions">
							<?php
							$actions_html = '';
							if ( ! empty( $job_actions[ $job->ID ] ) ) {
								foreach ( $job_actions[ $job->ID ] as $action => $value ) {
									$action_url = add_query_arg( [
										'action' => $action,
										'job_id' => $job->ID
									] );
									if ( $value['nonce'] ) {
										$action_url = wp_nonce_url( $action_url, $value['nonce'] );
									}
									$actions_html .= '<a href="' . esc_url( $action_url ) . '" class=" jm-dashboard-action jm-ui-button--link job-dashboard-action-' . esc_attr( $action ) . '">' . esc_html( $value['label'] ) . '</a>' . "\n";
								}
							}

							echo UI_Elements::actions_menu( $actions_html );
							?>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>
	<?php get_job_manager_template( 'pagination.php', [ 'max_num_pages' => $max_num_pages ] ); ?>
</div>
