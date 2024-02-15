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
 * @version     $$next-version$$
 *
 * @since $$next-version$$ Switched to a responsive layout. job_manager_job_dashboard_column_{$key} action is called for all columns.
 * @since 1.34.4 Available job actions are passed in an array (`$job_actions`, keyed by job ID) and not generated in the template.
 * @since 1.35.0 Switched to new date functions.
 *
 * @var array     $job_dashboard_columns Array of the columns to show on the job dashboard page.
 * @var int       $max_num_pages Maximum number of pages
 * @var WP_Post[] $jobs Array of job post results.
 * @var array     $job_actions Array of actions available for each job.
 * @var string    $search_input Search input.
 */

use WP_Job_Manager\UI\Modal_Dialog;
use WP_Job_Manager\UI\Notice;
use WP_Job_Manager\UI\UI_Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$submit_job_form_page_id = get_option( 'job_manager_submit_job_form_page_id' );
?>

<div id="job-manager-job-dashboard" class="alignwide">
	<div class="jm-dashboard__intro">
		<div class="jm-dashboard__filters">
			<form method="GET" action="" class="jm-form">
				<div style="display: flex; gap: 12px;">
					<input type="search" name="search" class="jm-ui-input--search-icon"
						placeholder="<?php esc_attr_e( 'Search', 'wp-job-manager' ); ?>"
						value="<?php echo esc_attr( $search_input ); ?>"
						aria-label="<?php esc_attr_e( 'Search', 'wp-job-manager' ); ?>" />
				</div>
			</form>
		</div>
		<div class="jm-dashboard__actions">
			<?php if ( job_manager_user_can_submit_job_listing() ) : ?>
				<a class="jm-ui-button"
					href="<?php echo esc_url( get_permalink( $submit_job_form_page_id ) ); ?>"><span><?php esc_html_e( 'Add Job', 'wp-job-manager' ); ?></span></a>
			<?php endif; ?>
		</div>
	</div>
	<div class="job-manager-jobs jm-dashboard">
		<?php if ( ! $jobs ) : ?>
			<div
				class="jm-dashboard-empty">
				<?php echo Notice::dialog(
					[
						'message' => $search_input
							// translators: Placeholder is the search term.
							? sprintf( __( 'No results found for "%s".', 'wp-job-manager' ), $search_input )
							: __( 'You do not have any active listings.', 'wp-job-manager' )
					]
				); ?>
			</div>
		<?php else : ?>
			<div class="jm-dashboard-header">
				<?php foreach ( $job_dashboard_columns as $key => $column ) : ?>
					<div
						class="jm-dashboard-job-column <?php echo esc_attr( $key ); ?>"><?php echo esc_html( $column ); ?></div>
				<?php endforeach; ?>
				<div class="jm-dashboard-job-column actions"><?php esc_html_e( 'Actions', 'wp-job-manager' ); ?></div>
			</div>
			<div class="jm-dashboard-rows">
				<?php foreach ( $jobs as $job ) : ?>
					<div class="jm-dashboard-job">
						<?php foreach ( $job_dashboard_columns as $key => $column ) : ?>
							<div class="jm-dashboard-job-column <?php echo esc_attr( $key ); ?>"
								aria-label="<?php echo esc_attr( $column ); ?>">
								<?php

								switch ( $key ) {
									case 'job_title':
										echo '<a class="job-title" data-job-id="' . esc_attr( $job->ID ) . '" href="' . esc_url( get_permalink( $job->ID ) ) . '">' . esc_html( get_the_title( $job ) ?? $job->ID ) . '</a>';
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
										'job_id' => $job->ID,
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
			</div>
		<?php endif; ?>
	</div>
	<?php get_job_manager_template( 'pagination.php', [ 'max_num_pages' => $max_num_pages ] ); ?>

	<?php
	$overlay = new Modal_Dialog( [
		'id'    => 'jmDashboardOverlay',
		'class' => 'jm-dashboard__overlay',
	] );

	echo $overlay->render( '' ); ?>
</div>
