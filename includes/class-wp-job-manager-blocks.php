<?php
/**
 * Handles Job Manager's Gutenberg Blocks.
 *
 * @package wp-job-manager
 * @since 1.32.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WP_Job_Manager_Blocks.
 */
class WP_Job_Manager_Blocks {
	/**
	 * The static instance of the WP_Job_Manager_Blocks
	 *
	 * @var self
	 */
	private static $instance = null;

	/**
	 * Singleton instance getter
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new WP_Job_Manager_Blocks();
		}

		return self::$instance;
	}

	/**
	 * Instance constructor
	 */
	private function __construct() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		add_action( 'init', [ $this, 'register_blocks' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enable_legacy_widget_block' ] );
	}

	/**
	 * Register all Gutenblocks
	 */
	public function register_blocks() {
		// Add script includes for gutenblocks.
	}

	/**
	 * Enable the core Legacy Widget block in the Site Editor.
	 *
	 * On a block theme there is no classic Widgets screen, and the Site Editor
	 * does not expose the Legacy Widget block by default, so there is no UI to
	 * insert the plugin's classic widgets (Recent Jobs, Featured Jobs). Opting
	 * the block in here restores that path. Scoped to the Site Editor screen so
	 * the post/page editor is left untouched.
	 *
	 * The Site Editor registers `core/legacy-widget` itself with
	 * `supports.inserter: false`, so calling `registerLegacyWidgetBlock()` here
	 * would be a duplicate registration and log "Block core/legacy-widget is
	 * already registered". Instead, flip the inserter support flag on core's own
	 * registration via the `blocks.registerBlockType` filter.
	 *
	 * @see https://developer.wordpress.org/block-editor/how-to-guides/widgets/legacy-widget-block/
	 * @see https://developer.wordpress.org/block-editor/reference-guides/filters/block-filters/
	 */
	public function enable_legacy_widget_block() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || 'site-editor' !== $screen->id ) {
			return;
		}

		if ( ! wp_script_is( 'wp-widgets', 'registered' ) ) {
			return;
		}

		// `wp-widgets` loads before `wp-edit-site`, so the filter is in place
		// before the Site Editor registers the block.
		wp_enqueue_script( 'wp-widgets' );
		wp_add_inline_script(
			'wp-widgets',
			'wp.hooks.addFilter(
				"blocks.registerBlockType",
				"wp-job-manager/enable-legacy-widget-inserter",
				function ( settings, name ) {
					if ( "core/legacy-widget" !== name ) {
						return settings;
					}
					return Object.assign( {}, settings, {
						supports: Object.assign( {}, settings.supports, { inserter: true } ),
					} );
				}
			);'
		);
	}
}

WP_Job_Manager_Blocks::get_instance();
