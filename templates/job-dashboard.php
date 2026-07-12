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
 * @version     2.5.0
 *
 * @since 2.3.0 Switched to a responsive layout. job_manager_job_dashboard_column_{$key} action is called for all columns.
 * @since 1.34.4 Available job actions are passed in an array (`$job_actions`, keyed by job ID) and not generated in the template.
 * @since 1.35.0 Switched to new date functions.
 * @since 2.5.0 Jobs can be grouped by status using group_by_status shortcode attribute.
 *
 * @var array     $job_dashboard_columns Array of the columns to show on the job dashboard page.
 * @var int       $max_num_pages Maximum number of pages.
 * @var WP_Post[] $jobs Array of job post results.
 * @var array     $job_actions Array of actions available for each job.
 * @var string    $search_input Search input.
 * @var bool      $group_by_status True if jobs should be grouped by status.
 * @var array     $job_groups Array of jobs grouped by status key.
 */

use WP_Job_Manager\Job_Overlay;
use WP_Job_Manager\UI\Notice;
use WP_Job_Manager\UI\UI_Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$submit_job_form_page_id = get_option( 'job_manager_submit_job_form_page_id' );

$group_labels = [
	'active'   => __( 'Active', 'wp-job-manager' ),
	'pending'  => __( 'Pending', 'wp-job-manager' ),
	'inactive' => __( 'Inactive', 'wp-job-manager' ),
];

/**
 * Render a single dashboard job row.
 *
 * @param WP_Post $job The job post object.
 */
$render_row = function ( $job ) use ( $job_dashboard_columns, $job_actions ) {
	?>
	<div class="jm-dashboard-job">
		<?php foreach ( $job_dashboard_columns as $key => $column ) : ?>
			<div class="jm-dashboard-job-column <?php echo esc_attr( $key ); ?>"
				aria-label="<?php echo esc_attr( $column ); ?>">
				<div class="jm-dashboard-job-column-label"><?php echo esc_html( $column ); ?></div>
				<?php do_action( 'job_manager_job_dashboard_column_' . $key, $job ); ?>
			</div>
		<?php endforeach; ?>
		<div class="jm-dashboard-job-column actions job-dashboard-job-actions">
			<?php do_action( 'job_manager_job_dashboard_column_actions', $job, $job_actions[ $job->ID ] ?? [] ); ?>
			<?php
			$actions_html = '';
			if ( ! empty( $job_actions[ $job->ID ] ) ) {
				foreach ( $job_actions[ $job->ID ] as $action ) {
					$actions_html .= '<a href="' . esc_url( $action['url'] ) . '" class=" jm-dashboard-action jm-ui-button--link job-dashboard-action-' . esc_attr( $action['name'] ) . '">' . esc_html( $action['label'] ) . '</a>' . "\n";
				}
			}

			echo UI_Elements::actions_menu( $actions_html );
			?>
		</div>
	</div>
	<?php
};

?>
<div id="job-manager-job-dashboard" class="alignwide jm-dashboard jm-ui">
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
	<?php $table_class = count( $job_dashboard_columns ) > 4 ? 'jm-dashboard-table--large' : ''; ?>
	<div class="job-manager-jobs jm-dashboard-table <?php echo esc_attr( $table_class ); ?>">
		<?php if ( $group_by_status && ! empty( $job_groups ) ) : ?>
			<?php $group_order = [ 'active', 'pending', 'inactive' ]; ?>
			<?php foreach ( $group_order as $group_key ) : ?>
				<?php
				if ( empty( $job_groups[ $group_key ] ) ) {
					continue;
				}

				$label = $group_labels[ $group_key ] ?? $group_key;
				$count = count( $job_groups[ $group_key ] );
				$open  = 'inactive' !== $group_key;
				?>
				<details class="jm-dashboard-group jm-dashboard-group--<?php echo esc_attr( $group_key ); ?>" <?php echo $open ? 'open' : ''; ?>>
					<summary class="jm-dashboard-group__summary">
						<span class="jm-dashboard-group__label"><?php echo esc_html( $label ); ?></span>
						<span class="jm-dashboard-group__count">(<?php echo esc_html( (string) $count ); ?>)</span>
					</summary>
					<div class="jm-dashboard-header">
						<?php foreach ( $job_dashboard_columns as $key => $column ) : ?>
							<div class="jm-dashboard-job-column jm-dashboard-job-column-label <?php echo esc_attr( $key ); ?>">
								<?php echo esc_html( $column ); ?>
							</div>
						<?php endforeach; ?>
						<div class="jm-dashboard-job-column jm-dashboard-job-column-label actions">
							<?php esc_html_e( 'Actions', 'wp-job-manager' ); ?>
						</div>
					</div>
					<div class="jm-dashboard-group__rows">
						<?php foreach ( $job_groups[ $group_key ] as $job ) : ?>
							<?php $render_row( $job ); ?>
						<?php endforeach; ?>
					</div>
				</details>
			<?php endforeach; ?>
		<?php elseif ( ! $jobs ) : ?>
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
					<div class="jm-dashboard-job-column jm-dashboard-job-column-label <?php echo esc_attr( $key ); ?>">
						<?php echo esc_html( $column ); ?>
					</div>
				<?php endforeach; ?>
				<div class="jm-dashboard-job-column jm-dashboard-job-column-label actions">
					<?php esc_html_e( 'Actions', 'wp-job-manager' ); ?>
				</div>
			</div>
			<div class="jm-dashboard-rows">
				<?php foreach ( $jobs as $job ) : ?>
					<?php $render_row( $job ); ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php get_job_manager_template( 'pagination.php', [ 'max_num_pages' => $max_num_pages ] ); ?>
</div>
