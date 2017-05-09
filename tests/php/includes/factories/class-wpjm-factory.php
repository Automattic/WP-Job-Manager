<?php
/**
 * Class WPJM Factory
 *
 * This class takes care of creating testing data for the WPJM Unit tests
 *
 * @since 1.26
 */
class WPJM_Factory extends WP_UnitTest_Factory {
	public $job_listing;

	/**
	 * Constructor
	 */
	public function __construct() {
		// construct the parent
		parent::__construct();
		require_once( dirname( __FILE__ ) . '/class-wp-unittest-factory-for-job-listing.php' );
		$this->job_listing = new WP_UnitTest_Factory_For_Job_Listing( $this );
	}
}
