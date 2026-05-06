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
 * @version     2.2.0
 *
 *
 * @var array  $options All arguments of the notice.
 * @var array  $classes Classes for the notice wrapper.
 * @var string $title Notice title.
 * @var string $icon_html Rendered icon HTML.
 * @var string $message Message text or HTML.
 * @var string $content_html Additional content HTML.
 * @var array  $actions_html Rendered HTML for buttons and links.
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$has_actions_footer = ! ! $actions_html;

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

<div class="jm-notice <?php echo esc_attr( implode( ' ', $classes ) ); ?>" role="status">
	<?php if ( $title ) : ?>
		<div class="jm-notice__header">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered by UI_Elements::icon(), which sanitizes its own input.
			echo $icon_html;
			?>
			<div class="jm-notice__title"><?php echo esc_html( $title ); ?></div>
		</div>
	<?php endif; ?>
	<?php if ( $message ) : ?>
		<div
			class="jm-notice__message-wrap">
			<?php if ( ! $title && $icon_html ) : ?>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered by UI_Elements::icon().
				echo $icon_html;
				?>
			<?php endif; ?>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered by UI_Elements::icon().
			echo $message_icon_html ?? '';
			?>
			<div
				class="jm-notice__message <?php echo esc_attr( $content_html ? 'has-details' : '' ); ?> "><?php echo wp_kses_post( $message ); ?></div>
		</div>
	<?php endif; ?>
	<?php if ( $content_html ) : ?>
		<div class="jm-notice__details">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Caller is responsible for escaping per Notice::render docblock; wp_kses_post() would strip onclick handlers and SVG icons.
			echo $content_html;
			?>
		</div>
	<?php endif; ?>
	<?php if ( $has_actions_footer ) : ?>
		<div class="jm-notice__footer">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered by UI_Elements::actions(); wp_kses_post() would strip onclick handlers used for modal open/close.
			echo $actions_html;
			?>
		</div>
	<?php endif; ?>
</div>
