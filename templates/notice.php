<?php
/**
 * Notice.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     wp-job-manager
 * @category    Template
 * @version     $$next-version$$
 *
 *
 * @var array  $options All arguments of the notice.
 * @var array  $classes Classes for the notice wrapper.
 * @var string $title Notice title.
 * @var string $icon_html Rendered icon HTML.
 * @var string $message Message text or HTML.
 * @var string $details Additional content HTML.
 * @var array  $buttons_html Array of rendered HTML for buttons.
 * @var array  $links_html Array of rendered HTML for links.
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$has_actions_footer = $buttons_html || $links_html;

if ( $has_actions_footer ) {
	$classes[] = 'has-actions';
}

if ( $title ) {
	$classes[] = 'has-header';
}

if ( in_array( 'message-icon', $classes, true ) ) {
	$message_icon_html = $icon_html;
	$icon_html         = '';
}

?>

<div class="jm-notice <?php echo esc_attr( implode( ' ', $classes ) ); ?>">
	<?php if ( $title ) : ?>
		<div class="jm-notice__header">
			<?php echo $icon_html; ?>
			<div class="jm-notice__title"><?php echo esc_html( $title ); ?></div>
		</div>
	<?php endif; ?>
	<?php if ( $message ) : ?>
		<div
			class="jm-notice__message-wrap">
			<?php if ( ! $title && $icon_html ) : ?>
				<?php echo $icon_html; ?>
			<?php endif; ?>
			<?php echo $message_icon_html ?? ''; ?>
			<div
				class="jm-notice__message <?php echo esc_attr( $details ? 'has-details' : '' ); ?> "><?php echo wp_kses_post( $message ); ?></div>
		</div>
	<?php endif; ?>
	<?php if ( $details ) : ?>
		<div class="jm-notice__details"><?php echo wp_kses_post( $details ); ?></div>
	<?php endif; ?>
	<?php if ( $has_actions_footer ) : ?>
		<div class="jm-notice__footer">
			<?php if ( $buttons_html ) : ?>
				<div class="jm-notice__buttons">
					<?php echo implode( '', $buttons_html ); ?>
				</div>
			<?php endif; ?>
			<?php if ( $links_html ) : ?>
				<div class="jm-notice__actions">
					<?php echo implode( '', $links_html ); ?>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
