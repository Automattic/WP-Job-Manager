<?php
/**
 * Job stats
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     wp-job-manager
 * @category    Template
 * @version     $$next-version$$
 *
 * @var WP_Post $job Array of job post results.
 * @var array   $stats Total stats grouped by section.
 * @var array   $chart Total stats grouped by section.
 */

use WP_Job_Manager\UI\UI_Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>


<div class="jm-job-stats">
	<div class="jm-job-chart">
		<div class="jm-stat-section-header">
			<?php esc_html_e( 'Daily Views', 'wp-job-manager' ); ?>
		</div>
		<div class="jm-chart-bars">
		<?php foreach ( $chart['values'] as $day ) : ?>
				<div class="jm-chart-bar <?php echo esc_attr( $day['class'] ?? '' ); ?>" aria-describedby="jm-chart-bar-tooltip-<?php echo esc_attr( $day['date'] ); ?>">
					<div class="jm-chart-bar-tooltip" id="jm-chart-bar-tooltip-<?php echo esc_attr( $day['date'] ); ?>">
						<div class="jm-ui-row">
						<strong><?php echo esc_html( $day['date'] ); ?></strong>
						</div>
						<div class="jm-ui-row">
							<?php esc_html_e( 'Page Views', 'wp-job-manager' ); ?>
							<strong><?php echo esc_html( $day['views'] ); ?></strong>
						</div>
						<div class="jm-ui-row">
							<?php esc_html_e( 'Unique Visitors', 'wp-job-manager' ); ?>
							<strong><?php echo esc_html( $day['uniques'] ); ?></strong>
						</div>


					</div>
					<div class="jm-chart-bar-value"
						style="height: <?php echo esc_attr( ( $day['views'] / $chart['max'] ) * 100 ); ?>%;"></div>
					<div class="jm-chart-bar-inner-value"
						style="height: <?php echo esc_attr( ( $day['uniques'] / $chart['max'] ) * 100 ); ?>%;"></div>
				</div>
		<?php endforeach; ?>
		</div>
	</div>

	<div class="jm-job-stat-details jm-ui-row">
		<?php foreach ( $stats as $column ) : ?>
			<div class="jm-ui-col">
				<?php foreach ( $column as $section ) : ?>
					<div class="jm-stat-section">
						<div class="jm-stat-section-header">
							<?php echo esc_html( $section['title'] ); ?>
						</div>
						<?php foreach ( $section['stats'] as $stat ) : ?>
							<div class="jm-stat-row jm-ui-row">
								<?php if ( $stat['icon'] ) {
									echo UI_Elements::icon( $stat['icon'], $stat['label'] );
								} ?>
								<div class="jm-stat-label">
									<?php echo esc_html( $stat['label'] ); ?>
								</div>
								<div class="jm-stat-value">
									<?php echo esc_html( $stat['value'] ); ?>
									<?php if ( $stat['percent'] ) : ?>
										<span
											class="jm-stat-value-percent"><?php echo esc_html( $stat['percent'] ); ?>%</span>
									<?php endif; ?>
								</div>
								<?php if ( isset( $stat['background'] ) ) : ?>
									<span
										class="jm-stat-background"
										style="width: <?php echo esc_attr( $stat['background'] . '%' ); ?>;"></span>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>

	</div>
</div>
