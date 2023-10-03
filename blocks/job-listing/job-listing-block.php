<?php

namespace WP_Job_Manager\Blocks\JobListing;

use WP_Job_Manager\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class for blocks rendering job_listing properties.
 */
class JobListingBlock extends Blocks\DynamicBlock {

	/**
	 * ID of the job listing being rendered.
	 *
	 * @var int
	 */
	protected $job_id;

	/**
	 * Ensure there is a job_listing post ID in the context or skip rendering.
	 */
	public function maybe_render_block( $attributes, $content, $block ) {

		global $post;

		$this->job_id = $block->context['postId'] ?? $attributes['postId'] ?? $post->ID ?? null;

		if ( empty( $this->job_id ) || 'job_listing' !== get_post_type( $this->job_id ) ) {
			return '';
		}

		return parent::maybe_render_block( $attributes, $content, $block );
	}


}
