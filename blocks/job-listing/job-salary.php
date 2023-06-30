<?php

namespace WP_Job_Manager\Blocks\JobListing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JobSalary extends JobListingBlock {

	public const PATH = 'job-listing/job-salary';

	public function render( $attributes, $content, $block ) {

		$output = the_job_salary( '', '', false, $this->job_id );
		return esc_html( $output );

	}
}
