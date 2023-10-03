<?php

namespace WP_Job_Manager\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class for dynamic blocks.
 */
class DynamicBlock {

	public const PATH = '';

	private static $instance = [];

	public static function instance() {

		if ( ! static::$instance[ static::class ] ) {
			static::$instance[ static::class ] = new static();
		}

		return static::$instance[ static::class ];
	}

	public function __construct() {

		add_action( 'init', array( $this, 'register' ), 10, 0 );
	}

	public function register( $args = [] ) {

		$path = dirname( __FILE__ ) . '/' . static::PATH . '.block.json';
		if ( ! file_exists( $path ) ) {
			return;
		}

		register_block_type_from_metadata( $path, array_merge(
			[
				'editor_script_handles' => [ 'wp-job-manager-blocks' ],
				'render_callback'       => [ $this, 'maybe_render_block' ],
			],
			$args ) );

	}

	public function maybe_render_block( $attributes, $content, $block ) {
		return $this->render( $attributes, $content, $block );
	}

	public function render( $attributes, $content, $block ) {
		return $content;
	}


}
