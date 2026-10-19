<?php
/**
 * Job dashboard navigation.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/job-dashboard-nav.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     wp-job-manager
 * @category    Template
 * @version     $$next-version$$
 *
 * @since $$next-version$$ Added to support custom job dashboard views.
 *
 * @var array  $nav_items    Navigation items keyed by view name.
 * @var string $current_view The view currently being displayed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$nav_items = isset( $nav_items ) && is_array( $nav_items ) ? $nav_items : [];

if ( count( $nav_items ) < 2 ) {
	return;
}

$current_view = isset( $current_view ) ? $current_view : '';
?>
<nav class="jm-dashboard-nav" aria-label="<?php esc_attr_e( 'Job dashboard', 'wp-job-manager' ); ?>">
	<ul class="jm-dashboard-nav__items">
		<?php foreach ( $nav_items as $view => $nav_item ) : ?>
			<?php $is_current = $view === $current_view; ?>
			<li class="jm-dashboard-nav__item">
				<a class="jm-dashboard-nav__link<?php echo esc_attr( $is_current ? ' jm-dashboard-nav__link--current' : '' ); ?>"
					href="<?php echo esc_url( $nav_item['url'] ); ?>"
					<?php echo $is_current ? 'aria-current="page"' : ''; ?>>
					<?php echo esc_html( $nav_item['label'] ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
