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
 */

use WP_Job_Manager\UI\UI_Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>

<div class="jm-job-stats jm-ui-row">
	<?php foreach ( $stats as $column ) : ?>
		<div class="jm-ui-col">
			<?php foreach ( $column as $section ) : ?>
				<div class="jm-stat-section">
					<div class="jm-stat-section-header">
						<?php echo esc_html( $section['title'] ); ?>
					</div>
					<?php foreach ( $section['stats'] as $stat ) : ?>
						<div class="jm-stat-row jm-ui-row">
							<?php if ( $stat['icon'] ) { echo UI_Elements::icon( $stat['icon'], $stat['label'] ); } ?>
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
									class="jm-stat-background" style="width: <?php echo esc_attr( $stat['background'] . '%' ); ?>;"></span>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>

</div>
