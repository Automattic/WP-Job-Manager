<?php
/**
 * Job listing title block.
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager\Blocks\JobListing;
//
//if ( ! defined( 'ABSPATH' ) ) {
//	exit;
//}

/**
 * Job listing title block.
 */
class JobTitle extends JobListingBlock {

	public const PATH = 'job-listing/job-title';

	/**
	 * Render block.
	 *
	 * @param array     $attributes
	 * @param string    $content
	 * @param \WP_Block $block
	 *
	 * @return string
	 */
	public function render( $attributes, $content, $block ) {

		$wrapper = get_block_wrapper_attributes();

		$job_title = wpjm_get_the_job_title( $this->job_id );

		return '<div ' . $wrapper . '>' . wp_kses_post( $job_title ) . '</div>';

	}
}
