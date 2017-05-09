<?php

class WP_Test_WP_Job_Manager_Ajax extends WPJM_BaseTest {
	/**
	 * Tests the WP_Job_Manager_Ajax::instance() always returns the same `WP_Job_Manager_Ajax` instance.
	 *
	 * @since 1.26.0
	 * @covers WP_Job_Manager_Ajax::instance
	 */
	public function test_wp_job_manager_ajax_instance() {
		$job_manager_ajax_instance = WP_Job_Manager_Ajax::instance();
		// check the class
		$this->assertInstanceOf( 'WP_Job_Manager_Ajax', $job_manager_ajax_instance, 'Job Manager Ajax object is instance of WP_Job_Manager_Ajax class' );

		// check it always returns the same object
		$this->assertSame( WP_Job_Manager_Ajax::instance(), $job_manager_ajax_instance, 'WP_Job_Manager_Ajax::instance() must always return the same object' );
	}

	/**
	 * @since 1.26.0
	 * @covers WP_Job_Manager_Ajax::get_endpoint
	 */
	public function test_get_endpoint() {
		global $wp_rewrite;
		$original_permalink = get_option( 'permalink_structure' );

		// Test an "almost pretty" permalink
		update_option( 'permalink_structure', '/index.php/%postname%/' );
		$this->assertEquals( '/index.php/jm-ajax/%%endpoint%%/', WP_Job_Manager_Ajax::get_endpoint() );

		// Test an pretty permalink
		update_option( 'permalink_structure', '/%postname%/' );
		$this->assertEquals( '/jm-ajax/%%endpoint%%/', WP_Job_Manager_Ajax::get_endpoint() );

		// Test an ugly permalink
		update_option( 'permalink_structure', null );
		$this->assertEquals( '/?jm-ajax=%%endpoint%%', WP_Job_Manager_Ajax::get_endpoint() );

		update_option( 'permalink_structure', $original_permalink );
	}

	/**
	 * @since 1.26.0
	 * @covers WP_Job_Manager_Ajax::do_jm_ajax
	 */
	public function test_do_jm_ajax() {
		global $wp_query;
		$bootstrap = WPJM_Unit_Tests_Bootstrap::instance();
		include_once( $bootstrap->includes_dir . '/stubs/class-wpjm-ajax-action-stub.php' );
		$this->assertTrue( class_exists( 'WPJM_Ajax_Action_Stub' ) );
		$handler = new WPJM_Ajax_Action_Stub();
		add_filter( 'wp_die_ajax_handler', array( $this, 'return_do_not_die' ) );
		$wp_query->set( 'jm-ajax', $handler->action );
		$this->assertFalse( $handler->fired );
		WP_Job_Manager_Ajax::do_jm_ajax();
		$this->assertTrue( $handler->fired );
		remove_filter( 'wp_die_ajax_handler', array( $this, 'return_do_not_die' ) );
	}

	/**
	 * @since 1.26.0
	 * @covers WP_Job_Manager_Ajax::get_listings
	 */
	public function test_get_listings() {
		$this->set_up_job_listing_search_request();
		$published = $this->factory->job_listing->create_many( 2 );
		$draft = $this->factory->job_listing->create_many( 2, array(
			'post_status' => 'expired',
			'meta_input' => array(),
		) );
		$instance = WP_Job_Manager_Ajax::instance();

		// Run the action
		add_filter( 'wp_die_ajax_handler', array( $this, 'return_do_not_die' ) );
		ob_start();
		$instance->get_listings();
		$result = json_decode( ob_get_clean(), true );
		remove_filter( 'wp_die_ajax_handler', array( $this, 'return_do_not_die' ) );
		$this->tear_down_job_listing_search_request();

		// Check result
		$this->assertTrue( $result['found_jobs'] );
		$this->assertEmpty( $result['showing'] );
		$this->assertInternalType( 'array', $result );
		$this->assertArrayHasKey( 'html', $result );

		// Make sure the HTML contains all the published post titles
		foreach ( $published as $post_id ) {
			$post = get_post( $post_id );
			$this->assertContains( $post->post_title, $result['html'] );
		}

		// Make sure the HTML does NOT contain any of the draft post titles
		foreach ( $draft as $post_id ) {
			$post = get_post( $post_id );
			$this->assertNotContains( $post->post_title, $result['html'] );
		}
	}

	/**
	 * @since 1.26.0
	 * @covers WP_Job_Manager_Ajax::get_listings
	 */
	public function test_get_listings_no_html() {
		$this->set_up_job_listing_search_request();
		$published = $this->factory->job_listing->create_many( 2 );
		$draft = $this->factory->job_listing->create_many( 2, array(
			'post_status' => 'expired',
			'meta_input' => array(),
		) );
		$instance = WP_Job_Manager_Ajax::instance();

		// Add no extra filters
		add_filter( 'job_manager_ajax_get_jobs_html_results', '__return_false' );
		add_filter( 'job_manager_get_listings_result', array( $this, 'load_last_jobs_result' ), 10, 2 );

		// Run the action
		add_filter( 'wp_die_ajax_handler', array( $this, 'return_do_not_die' ) );
		ob_start();
		$instance->get_listings();
		$result = json_decode( ob_get_clean(), true );
		remove_filter( 'wp_die_ajax_handler', array( $this, 'return_do_not_die' ) );
		$this->tear_down_job_listing_search_request();

		$this->assertInternalType( 'array', $result );
		$this->assertArrayNotHasKey( 'html', $result );
		$this->assertArrayHasKey( '_jobs', $result );
		$this->assertCount( 2, $result['_jobs'] );
		$this->assertTrue( $result['found_jobs'] );
		$this->assertEmpty( $result['showing'] );

		$job_ids = array();
		/**
		 * @var WP_Post $post
		 */
		foreach ( $result['_jobs'] as $post ) {
			$job_ids[] = $post['ID'];
		}

		sort( $job_ids );
		sort( $published );
		$this->assertSame( $job_ids, $published );
	}

	/**
	 * @since 1.26.0
	 * @covers WP_Job_Manager_Ajax::upload_file
	 */
	public function test_upload_file() {
		$instance = WP_Job_Manager_Ajax::instance();
		$iptc_file = DIR_TESTDATA . '/images/test-image-iptc.jpg';

		// Make a copy of this file as it gets moved during the file upload
		$tmp_name = wp_tempnam( $iptc_file );

		copy( $iptc_file, $tmp_name );

		$_FILES['upload'] = array(
			'tmp_name' => $tmp_name,
			'name'     => 'test-image-iptc.jpg',
			'type'     => 'image/jpeg',
			'error'    => 0,
			'size'     => filesize( $iptc_file ),
		);
		$this->assertFileExists( $tmp_name );

		// Add extra filters
		add_filter( 'submit_job_wp_handle_upload_overrides', array( $this, 'override_upload_action' ) );
		// Run the action
		add_filter( 'wp_die_ajax_handler', array( $this, 'return_do_not_die' ) );
		ob_start();
		$instance->upload_file();
		$result = json_decode( ob_get_clean(), true );
		remove_filter( 'wp_die_ajax_handler', array( $this, 'return_do_not_die' ) );
		unset( $_FILES['upload'] );

		// Check result
		$this->assertNotEmpty( $result );
		$this->assertArrayHasKey( 'files', $result );
		$this->assertCount( 1, $result['files'] );
		$this->assertArrayHasKey( 'name', $result['files'][0] );
		$this->assertArrayHasKey( 'file', $result['files'][0] );
		$this->assertStringStartsWith( 'test-image-iptc', $result['files'][0]['name'] );
		$this->assertFileExists( $result['files'][0]['file'] );

		// Cleanup
		@unlink( $result['files'][0]['file'] );
	}


	/**
	 * @since 1.26.0
	 * @covers WP_Job_Manager_Ajax::upload_file
	 */
	public function test_upload_bad_file() {
		$instance  = WP_Job_Manager_Ajax::instance();

		$_FILES['upload'] = array(
			'tmp_name' => null,
			'name'     => 'test-image-iptc-bad.jpg',
			'type'     => 'image/jpeg',
			'error'    => 1,
			'size'     => 0,
		);

		// Add extra filters
		add_filter( 'submit_job_wp_handle_upload_overrides', array( $this, 'override_upload_action' ) );
		// Run the action
		add_filter( 'wp_die_ajax_handler', array( $this, 'return_do_not_die' ) );
		ob_start();
		$instance->upload_file();
		$result = json_decode( ob_get_clean(), true );
		remove_filter( 'wp_die_ajax_handler', array( $this, 'return_do_not_die' ) );
		unset( $_FILES['upload'] );

		$this->assertNotEmpty( $result );
		$this->assertArrayHasKey( 'files', $result );
		$this->assertCount( 1, $result['files'] );
		$this->assertArrayHasKey( 'error', $result['files'][0] );
	}

	public function load_last_jobs_result( $result, $jobs ) {
		$result['_jobs'] = $jobs->get_posts();
		return $result;
	}

	public function override_upload_action( $args ) {
		$args['action'] = 'test-wpjm-upload';
		return $args;
	}

	private function set_up_job_listing_search_request() {
		$_REQUEST['search_location'] = null;
		$_REQUEST['search_keywords'] = null;
		$_REQUEST['search_categories'] = null;
		$_REQUEST['filter_job_type'] = null;
		$_REQUEST['orderby'] = null;
		$_REQUEST['order'] = null;
		$_REQUEST['page'] = 1;
		$_REQUEST['per_page'] = 100;
	}

	private function tear_down_job_listing_search_request() {
		unset( $_REQUEST['search_location'] );
		unset( $_REQUEST['search_keywords'] );
		unset( $_REQUEST['search_categories'] );
		unset( $_REQUEST['filter_job_type'] );
		unset( $_REQUEST['orderby'] );
		unset( $_REQUEST['order'] );
		unset( $_REQUEST['page'] );
		unset( $_REQUEST['per_page'] );
	}
}
