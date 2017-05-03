<?php

class WP_UnitTest_Factory_For_Job_Listing extends WP_UnitTest_Factory_For_Post {
	function __construct( $factory = null ) {
		parent::__construct( $factory );
		$this->default_generation_definitions = array(
			'post_status' => 'publish',
			'post_title' => new WP_UnitTest_Generator_Sequence( 'Job Listing title %s' ),
			'post_content' => new WP_UnitTest_Generator_Sequence( 'Job Listing content %s' ),
			'post_excerpt' => new WP_UnitTest_Generator_Sequence( 'Job Listing excerpt %s' ),
			'post_type' => 'job_listing'
		);
	}
}
