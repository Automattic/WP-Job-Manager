<?php
/**
 * Server side for the Jobs block.
 *
 * @package wp-job-manager
 * @since 1.32.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WP_Job_Manager_Block_Jobs
 */
class WP_Job_Manager_Block_Jobs_Shortcode {
	/**
	 * Get the WP_Block_Type object to register
	 */
	public static function get_block_type() {
		self::register_block_assets();

		// Jobs block
		return new WP_Block_Type( 'wp-job-manager/jobs', array(
			'editor_script'   => array(
				'wp-job-manager-block-jobs',
				'wp-job-manager-ajax-filters',
			),
			'editor_style'    => 'wp-job-manager-frontend',
			'render_callback' => array(
				'WP_Job_Manager_Block_Jobs_Shortcode', 'render'
			),
			'attributes'      => self::get_block_attributes(),
		) );
	}

	private static function register_block_assets() {
		wp_register_script(
			'wp-job-manager-block-jobs',
			JOB_MANAGER_PLUGIN_URL . '/assets/build/blocks/jobs.js',
			array(
				'wp-blocks',
				'wp-element',
				'wp-editor',
				'wp-components',
			),
			'1.0.0'
		);
	}

	private static function get_block_attributes() {
		// TODO: these need to be changed to match the shortcode.
		return array(
			'showFilters'        => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'keywords'           => array(
				'type'    => 'string',
				'default' => '',
			),
			'location'           => array(
				'type'    => 'string',
				'default' => '',
			),
			'perPage'            => array(
				'type'    => 'string',
				'default' => '',
			),
			'orderBy'            => array(
				'type'    => 'string',
				'default' => 'featured',
			),
			'order'              => array(
				'type'    => 'string',
				'default' => 'desc',
			),
			'showPagination'     => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'showCategories'     => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'featured'           => array(
				'type'    => 'string',
				'default' => '',
			),
			'filled'             => array(
				'type'    => 'string',
				'default' => '',
			),
			'showJobTypeFilters' => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'includedJobTypes'   => array(
				'type'    => 'string', // JSON object
				'default' => '{}',
			),
			'allJobTypes'        => array(
				'type'    => 'string', // JSON array
				'default' => '[]',
			)
		);
	}

	/**
	 * Render the jobs block.
	 */
	public static function render( $attributes ) {
		return do_shortcode( '[jobs]' );
	}
}
