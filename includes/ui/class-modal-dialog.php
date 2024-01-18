<?php
/**
 * UI Modal Dialog class.
 *
 * @package wp-job-manager
 * @since $$next-version$$
 */

namespace WP_Job_Manager\UI;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Modal dialog component for the frontend.
 *
 * @since $$next-version$$
 *
 * @internal This API is still under development and subject to change.
 * @access private
 */
class Modal_Dialog {

	/**
	 * Unique ID for the dialog.
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * Create a new dialog instance.
	 *
	 * @param array $options {
	 *    Optional. Options for the dialog.
	 *
	 * @type string $id Unique ID for the dialog. Auto-generated if left empty.
	 * }
	 */
	public function __construct( $options = [] ) {
		$options = wp_parse_args(
			$options,
			[
				'id' => null,
			]
		);

		$this->id = $options['id'] ?? 'jm' . uniqid();
	}

	/**
	 * Return an onclick handler to open the dialog.
	 *
	 * @return string
	 */
	public function open() {
		return esc_attr( $this->id . '.showModal(); event.preventDefault();' );
	}

	/**
	 * Render the dialog with the given HTML content.
	 *
	 * @param string $content Escaped HTML content.
	 *
	 * @return string HTML element
	 */
	public function render( $content ) {
		$id          = $this->id;
		$close_label = __( 'Close', 'wp-job-manager' );

		$content = str_replace( '{close}', $id . '.close(); event.preventDefault();', $content );

		return <<<HTML
<dialog class="jm-dialog" id="{$id}">
	<div class="jm-dialog-open">
		<div class="jm-dialog-backdrop" onclick="{$id}.close()"></div>
		<div class="jm-dialog-modal">
			{$content}
			<a href="#" role="button" class="jm-ui-button--icon jm-dialog-close" onclick="{$id}.close()" aria-label="{$close_label}"><span class="jm-ui-button__icon"></span></a>
		</div>
	</div>
</dialog>
HTML;

	}

}
