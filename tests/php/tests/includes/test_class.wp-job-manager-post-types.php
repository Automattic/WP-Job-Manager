<?php

class WP_Test_WP_Job_Manager_Post_Types extends WPJM_BaseTest {
	public function setUp() {
		parent::setUp();
		update_option( 'job_manager_enable_categories', 1 );
		update_option( 'job_manager_enable_types', 1 );
		unregister_post_type( 'job_listing' );
		$post_type_instance = WP_Job_Manager_Post_Types::instance();
		$post_type_instance->register_post_types();
		add_filter( 'job_manager_geolocation_enabled', '__return_false' );
	}

	public function tearDown() {
		parent::tearDown();
		add_filter( 'job_manager_geolocation_enabled', '__return_true' );
	}

	/**
	 * Tests the WP_Job_Manager_Post_Types::instance() always returns the same `WP_Job_Manager_API` instance.
	 *
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::instance
	 */
	public function test_wp_job_manager_post_types_instance() {
		$instance = WP_Job_Manager_Post_Types::instance();
		// check the class.
		$this->assertInstanceOf( 'WP_Job_Manager_Post_Types', $instance, 'Job Manager Post Types object is instance of WP_Job_Manager_Post_Types class' );

		// check it always returns the same object.
		$this->assertSame( WP_Job_Manager_Post_Types::instance(), $instance, 'WP_Job_Manager_Post_Types::instance() must always return the same object' );
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::job_content
	 */
	public function test_job_content() {
		global $wp_query;
		$instance = WP_Job_Manager_Post_Types::instance();
		$job_id   = $this->factory->job_listing->create();
		$post_id  = $this->factory->post->create();

		$jobs = $wp_query = new WP_Query(
			array(
				'p'         => $job_id,
				'post_type' => 'job_listing',
			)
		);
		$this->assertEquals( 1, $jobs->post_count );
		$this->assertTrue( $jobs->is_single );

		// First test out of the loop and verify it just returns the original content.
		$post                    = $jobs->posts[0];
		$post_content_unfiltered = $instance->job_content( $post->post_content );
		$this->assertEquals( $post->post_content, $post_content_unfiltered );

		while ( $jobs->have_posts() ) {
			$jobs->the_post();
			$post = get_post();
			$this->assertTrue( is_singular( 'job_listing' ), 'Is singular === true' );
			$this->assertTrue( in_the_loop(), 'In the loop' );
			$this->assertEquals( 'job_listing', $post->post_type, 'Result is a job listing' );

			$post_content_filtered = $instance->job_content( $post->post_content );
			$this->assertNotEquals( $post->post_content, $post_content_filtered );
			$this->assertContains( '<div class="single_job_listing"', $post_content_filtered );

			ob_start();
			the_content();
			$post_content_filtered = ob_get_clean();
			$this->assertNotEquals( $post->post_content, $post_content_filtered );
			$this->assertContains( '<div class="single_job_listing"', $post_content_filtered );
			$this->assertContains( $post->post_content, $post_content_filtered );
		}
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::job_feed
	 * @runInSeparateProcess
	 */
	public function test_job_feed_rss2() {
		$this->factory->job_listing->create_many( 5 );
		$feed = $this->do_job_feed();
		$xml  = xml_to_array( $feed );
		$this->assertNotEmpty( $xml );
		// Get all the <item> child elements of the <channel> element.
		$items = xml_find( $xml, 'rss', 'channel', 'item' );
		$this->assertEquals( 5, count( $items ) );
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::job_feed
	 * @runInSeparateProcess
	 */
	public function test_job_feed_rss2_2inrow() {
		$this->factory->job_listing->create_many( 5 );
		$feed = $this->do_job_feed();
		$xml  = xml_to_array( $feed );
		$this->assertNotEmpty( $xml );
		// Get all the <item> child elements of the <channel> element.
		$items = xml_find( $xml, 'rss', 'channel', 'item' );
		$this->assertEquals( 5, count( $items ) );
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::job_feed
	 * @runInSeparateProcess
	 */
	public function test_job_feed_location_search() {
		$this->factory->job_listing->create_many(
			5,
			array(
				'meta_input' => array(
					'_job_location' => 'Portland, OR, USA',
				),
			)
		);
		$seattle_job_id = $this->factory->job_listing->create(
			array(
				'meta_input' => array(
					'_job_location' => 'Seattle, WA, USA',
				),
			)
		);
		$chicago_job_id = $this->factory->job_listing->create(
			array(
				'meta_input' => array(
					'_job_location' => 'Chicago, IL, USA',
				),
			)
		);

		$_GET['search_location'] = 'Seattle';
		$feed                    = $this->do_job_feed();
		unset( $_GET['search_location'] );
		$xml = xml_to_array( $feed );
		$this->assertNotEmpty( $xml );
		// Get all the <item> child elements of the <channel> element.
		$items = xml_find( $xml, 'rss', 'channel', 'item' );
		$this->assertEquals( 1, count( $items ) );
		$this->assertHasRssItem( $items, $seattle_job_id );
		$this->assertNotHasRssItem( $items, $chicago_job_id );
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::job_feed
	 * @runInSeparateProcess
	 */
	public function test_job_feed_keyword_search() {
		$this->factory->job_listing->create_many( 3 );
		$dog_job_id  = $this->factory->job_listing->create(
			array(
				'post_title' => 'Dog Whisperer',
			)
		);
		$dino_job_id = $this->factory->job_listing->create(
			array(
				'post_title' => 'Dinosaur Whisperer Pro',
			)
		);

		$_GET['search_keywords'] = 'Dinosaur';
		$feed                    = $this->do_job_feed();
		unset( $_GET['search_keywords'] );
		$xml = xml_to_array( $feed );
		$this->assertNotEmpty( $xml );
		// Get all the <item> child elements of the <channel> element.
		$items = xml_find( $xml, 'rss', 'channel', 'item' );
		$this->assertEquals( 1, count( $items ) );
		$this->assertHasRssItem( $items, $dino_job_id );
		$this->assertNotHasRssItem( $items, $dog_job_id );
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::add_feed_query_args
	 */
	public function test_add_feed_query_args() {
		$instance = WP_Job_Manager_Post_Types::instance();
		$wp       = new WP_Query();
		$this->assertEmpty( $wp->query_vars );
		$wp->query_vars['feed'] = 'job_feed';
		$wp->is_feed            = true;
		$instance->add_feed_query_args( $wp );
		$this->assertCount( 2, $wp->query_vars );
		$this->assertArrayHasKey( 'post_type', $wp->query_vars );
		$this->assertEquals( 'job_listing', $wp->query_vars['post_type'] );
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::add_feed_query_args
	 */
	public function test_add_feed_query_args_if_not_feed() {
		$instance = WP_Job_Manager_Post_Types::instance();
		$wp       = new WP_Query();
		$this->assertEmpty( $wp->query_vars );
		$wp->query_vars['feed'] = 'job_feed';
		$wp->is_feed            = false;
		$instance->add_feed_query_args( $wp );
		$this->assertCount( 1, $wp->query_vars );
		$this->assertArrayHasKey( 'feed', $wp->query_vars );

		$wp = new WP_Query();
		$this->assertEmpty( $wp->query_vars );
		$wp->query_vars['feed'] = 'something-else';
		$wp->is_feed            = true;
		$instance->add_feed_query_args( $wp );
		$this->assertCount( 1, $wp->query_vars );
		$this->assertArrayHasKey( 'feed', $wp->query_vars );
		$this->assertArrayNotHasKey( 'post_type', $wp->query_vars );
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::job_feed_namespace
	 */
	public function test_job_feed_namespace() {
		$site_url = site_url();
		$instance = WP_Job_Manager_Post_Types::instance();
		ob_start();
		$instance->job_feed_namespace();
		$result = ob_get_clean();
		$this->assertEquals( 'xmlns:job_listing="' . $site_url . '"' . "\n", $result );
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::job_feed_item
	 */
	public function test_job_feed_item() {
		$instance       = WP_Job_Manager_Post_Types::instance();
		$new_jobs       = array();
		$type_a         = wp_create_term( 'Job Type A', 'job_listing_type' );
		$type_b         = wp_create_term( 'Job Type B', 'job_listing_type' );
		$new_job_args   = array();
		$new_job_args[] = array(
			'meta_input' => array(
				'_company_name' => 'Custom Company A',
			),
			'tax_input'  => array(
				'job_listing_type' => $type_a['term_id'],
			),
		);
		$new_job_args[] = array(
			'meta_input' => array(
				'_job_location' => 'Custom Location B',
				'_company_name' => '',
			),
			'tax_input'  => array(
				'job_listing_type' => $type_b['term_id'],
			),
		);
		$new_job_args[] = array(
			'meta_input' => array(
				'_job_location' => 'Custom Location A',
				'_company_name' => 'Custom Company B',
			),
			'tax_input'  => array(),
		);
		$new_jobs[]     = $this->factory->job_listing->create( $new_job_args[0] );
		$new_jobs[]     = $this->factory->job_listing->create( $new_job_args[1] );
		$new_jobs[]     = $this->factory->job_listing->create( $new_job_args[2] );
		$jobs           = $wp_query = new WP_Query(
			array(
				'post_type' => 'job_listing',
				'orderby'   => 'ID',
				'order'     => 'ASC',
			)
		);
		$this->assertEquals( count( $new_jobs ), $jobs->post_count );

		$index = 0;
		while ( $jobs->have_posts() ) {
			$has_location = ! empty( $new_job_args[ $index ]['meta_input']['_job_location'] );
			$has_company  = ! empty( $new_job_args[ $index ]['meta_input']['_company_name'] );
			$has_job_type = ! empty( $new_job_args[ $index ]['tax_input']['job_listing_type'] );
			$index++;

			$jobs->the_post();
			$post = get_post();
			ob_start();
			$instance->job_feed_item();
			$result = ob_get_clean();
			$this->assertNotEmpty( $result );
			$result     = '<item>' . $result . '</item>';
			$result_arr = xml_to_array( $result );
			$this->assertNotEmpty( $result_arr );
			$this->assertTrue( isset( $result_arr[0]['child'] ) );
			$this->assertCount( 2, $result_arr[0]['child'] );

			if ( $has_location ) {
				$job_location = get_the_job_location( $post );
				$this->assertContains( 'job_listing:location', $result );
				$this->assertContains( $job_location, $result );
			} else {
				$this->assertNotContains( 'job_listing:location', $result );
			}

			if ( $has_job_type ) {
				$job_type = current( wpjm_get_the_job_types( $post ) );
				$this->assertContains( 'job_listing:job_type', $result );
				$this->assertContains( $job_type->name, $result );
			} else {
				$this->assertNotContains( 'job_listing:job_type', $result );
			}

			if ( $has_company ) {
				$company_name = get_the_company_name( $post );
				$this->assertContains( $company_name, $result );
				$this->assertContains( 'job_listing:company', $result );
			} else {
				$this->assertNotContains( 'job_listing:company', $result );
			}
		}
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::check_for_expired_jobs
	 */
	public function test_check_for_expired_jobs() {
		$new_jobs              = array();
		$new_jobs['none']      = $this->factory->job_listing->create( array( 'meta_input' => array( '_job_expires' => '' ) ) );
		$new_jobs['yesterday'] = $this->factory->job_listing->create( array( 'meta_input' => array( '_job_expires' => date( 'Y-m-d', strtotime( '-1 day' ) ) ) ) );
		$new_jobs['ancient']   = $this->factory->job_listing->create( array( 'meta_input' => array( '_job_expires' => date( 'Y-m-d', strtotime( '-100 day' ) ) ) ) );
		$new_jobs['tomorrow']  = $this->factory->job_listing->create( array( 'meta_input' => array( '_job_expires' => date( 'Y-m-d', strtotime( '+1 day' ) ) ) ) );

		$instance = WP_Job_Manager_Post_Types::instance();
		$this->assertNotExpired( $new_jobs['none'] );
		$this->assertNotExpired( $new_jobs['yesterday'] );
		$this->assertNotExpired( $new_jobs['ancient'] );
		$this->assertNotExpired( $new_jobs['tomorrow'] );
		$instance->check_for_expired_jobs();
		$this->assertNotExpired( $new_jobs['none'] );
		$this->assertExpired( $new_jobs['yesterday'] );
		$this->assertExpired( $new_jobs['ancient'] );
		$this->assertNotExpired( $new_jobs['tomorrow'] );

		$this->factory->job_listing->set_post_age( $new_jobs['ancient'], '-100 days' );

		$instance->check_for_expired_jobs();
		$this->assertNotTrashed( $new_jobs['ancient'] );

		add_filter( 'job_manager_delete_expired_jobs', '__return_true' );
		$instance->check_for_expired_jobs();
		remove_filter( 'job_manager_delete_expired_jobs', '__return_true' );

		$this->assertTrashed( $new_jobs['ancient'] );
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::delete_old_previews
	 */
	public function test_delete_old_previews() {
		$new_jobs              = array();
		$new_jobs['now']       = $this->factory->job_listing->create( array( 'post_status' => 'preview' ) );
		$new_jobs['yesterday'] = $this->factory->job_listing->create(
			array(
				'post_status' => 'preview',
				'age'         => '-1 day',
			)
		);
		$new_jobs['29days']    = $this->factory->job_listing->create(
			array(
				'post_status' => 'preview',
				'age'         => '-29 days',
			)
		);
		$new_jobs['30days']    = $this->factory->job_listing->create(
			array(
				'post_status' => 'preview',
				'age'         => '-30 days',
			)
		);
		$new_jobs['31days']    = $this->factory->job_listing->create(
			array(
				'post_status' => 'preview',
				'age'         => '-31 days',
			)
		);
		$new_jobs['60days']    = $this->factory->job_listing->create(
			array(
				'post_status' => 'preview',
				'age'         => '-60 days',
			)
		);
		$this->assertPostStatus( 'preview', $new_jobs['now'] );
		$this->assertPostStatus( 'preview', $new_jobs['yesterday'] );
		$this->assertPostStatus( 'preview', $new_jobs['29days'] );
		$this->assertPostStatus( 'preview', $new_jobs['30days'] );
		$this->assertPostStatus( 'preview', $new_jobs['31days'] );
		$this->assertPostStatus( 'preview', $new_jobs['60days'] );

		$instance = WP_Job_Manager_Post_Types::instance();
		$instance->delete_old_previews();

		$this->assertPostStatus( 'preview', $new_jobs['now'] );
		$this->assertPostStatus( 'preview', $new_jobs['yesterday'] );
		$this->assertPostStatus( 'preview', $new_jobs['29days'] );
		$this->assertPostStatus( 'preview', $new_jobs['30days'] );
		$this->assertEmpty( get_post( $new_jobs['31days'] ) );
		$this->assertEmpty( get_post( $new_jobs['60days'] ) );
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::set_expirey
	 */
	public function test_set_expirey() {
		$post = get_post( $this->factory->job_listing->create() );
		$this->setExpectedDeprecated( 'WP_Job_Manager_Post_Types::set_expirey' );
		$instance = WP_Job_Manager_Post_Types::instance();
		$instance->set_expirey( $post );
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::set_expiry
	 */
	public function test_set_expiry_post() {
		$post                  = get_post( $this->factory->job_listing->create() );
		$instance              = WP_Job_Manager_Post_Types::instance();
		$_POST['_job_expires'] = $expire_date = date( 'Y-m-d', strtotime( '+10 days', current_time( 'timestamp' ) ) );
		$instance->set_expiry( $post );
		unset( $_POST['_job_expires'] );
		$this->assertEquals( $expire_date, get_post_meta( $post->ID, '_job_expires', true ) );
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::set_expiry
	 */
	public function test_set_expiry_calculate() {
		$post             = get_post( $this->factory->job_listing->create( array( 'meta_input' => array( '_job_duration' => 77 ) ) ) );
		$instance         = WP_Job_Manager_Post_Types::instance();
		$expire_date      = date( 'Y-m-d', strtotime( '+77 days', current_time( 'timestamp' ) ) );
		$expire_date_calc = calculate_job_expiry( $post->ID );
		$this->assertEquals( $expire_date, $expire_date_calc );
		$instance->set_expiry( $post );
		$this->assertEquals( $expire_date, get_post_meta( $post->ID, '_job_expires', true ) );
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::set_expiry
	 */
	public function test_set_expiry_past() {
		$post     = get_post( $this->factory->job_listing->create( array( 'meta_input' => array( '_job_expires' => '2008-01-01' ) ) ) );
		$instance = WP_Job_Manager_Post_Types::instance();
		$instance->set_expiry( $post );
		$this->assertEquals( '', get_post_meta( $post->ID, '_job_expires', true ) );
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::fix_post_name
	 */
	public function test_fix_post_name() {
		$instance = WP_Job_Manager_Post_Types::instance();
		// Legit.
		$data                 = array(
			'post_type'   => 'job_listing',
			'post_status' => 'pending',
			'post_name'   => 'Bad ABC',
		);
		$postarr              = array();
		$postarr['post_name'] = 'TEST 123';
		$data_fixed           = $instance->fix_post_name( $data, $postarr );
		$this->assertEquals( $postarr['post_name'], $data_fixed['post_name'] );

		// Bad Post Type.
		$data                 = array(
			'post_type'   => 'post',
			'post_status' => 'pending',
			'post_name'   => 'Bad ABC',
		);
		$postarr              = array();
		$postarr['post_name'] = 'TEST 123';
		$data_fixed           = $instance->fix_post_name( $data, $postarr );
		$this->assertEquals( $data['post_name'], $data_fixed['post_name'] );

		// Bad Post Status.
		$data                 = array(
			'post_type'   => 'job_listing',
			'post_status' => 'publish',
			'post_name'   => 'Bad ABC',
		);
		$postarr              = array();
		$postarr['post_name'] = 'TEST 123';
		$data_fixed           = $instance->fix_post_name( $data, $postarr );
		$this->assertEquals( $data['post_name'], $data_fixed['post_name'] );
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::maybe_add_geolocation_data
	 */
	public function test_get_permalink_structure() {
		$permalink_test = array(
			'job_base'      => 'job-test-a',
			'category_base' => 'job-cat-b',
			'type_base'     => 'job-type-c',
		);
		update_option( 'wpjm_permalinks', $permalink_test );
		$permalinks = WP_Job_Manager_Post_Types::get_permalink_structure();
		delete_option( 'wpjm_permalinks' );
		$this->assertEquals( 'job-test-a', $permalinks['job_rewrite_slug'] );
		$this->assertEquals( 'job-cat-b', $permalinks['category_rewrite_slug'] );
		$this->assertEquals( 'job-type-c', $permalinks['type_rewrite_slug'] );
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::update_post_meta
	 */
	public function test_update_post_meta() {
		$instance = WP_Job_Manager_Post_Types::instance();
		$bad_post = get_post(
			$this->factory->post->create(
				array(
					'menu_order' => 10,
					'meta_input' => array( '_featured' => 0 ),
				)
			)
		);

		$post = get_post(
			$this->factory->job_listing->create(
				array(
					'menu_order' => 10,
					'meta_input' => array( '_featured' => 0 ),
				)
			)
		);

		$instance->update_post_meta( 0, $bad_post->ID, '_featured', '1' );
		$bad_post = get_post( $bad_post->ID );
		$this->assertEquals( '10', $bad_post->menu_order );

		$instance->update_post_meta( 0, $post->ID, '_featured', '1' );
		$post = get_post( $post->ID );
		$this->assertEquals( '-1', $post->menu_order );

		$instance->update_post_meta( 0, $post->ID, '_featured', '0' );
		$post = get_post( $post->ID );
		$this->assertEquals( '0', $post->menu_order );
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::maybe_update_geolocation_data
	 */
	public function test_maybe_update_geolocation_data() {
		global $wp_actions;
		$instance = WP_Job_Manager_Post_Types::instance();
		$post     = get_post(
			$this->factory->job_listing->create(
				array(
					'menu_order' => 10,
					'meta_input' => array( '_featured' => 0 ),
				)
			)
		);
		unset( $wp_actions['job_manager_job_location_edited'] );
		$this->assertEquals( 0, did_action( 'job_manager_job_location_edited' ) );
		$instance->maybe_update_geolocation_data( 0, $post->ID, 'whatever', 1 );
		$this->assertEquals( 1, did_action( 'job_manager_job_location_edited' ) );
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::maybe_update_menu_order
	 */
	public function test_maybe_update_menu_order() {
		$instance = WP_Job_Manager_Post_Types::instance();
		$post     = get_post(
			$this->factory->job_listing->create(
				array(
					'menu_order' => 10,
					'meta_input' => array( '_featured' => 0 ),
				)
			)
		);

		$instance->maybe_update_menu_order( 0, $post->ID, '_featured', '1' );
		$post = get_post( $post->ID );
		$this->assertEquals( '-1', $post->menu_order );

		$instance->maybe_update_menu_order( 0, $post->ID, '_featured', '0' );
		$post = get_post( $post->ID );
		$this->assertEquals( '0', $post->menu_order );
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::maybe_generate_geolocation_data
	 */
	public function test_maybe_generate_geolocation_data() {
		$post = get_post( $this->factory->job_listing->create() );
		$this->setExpectedDeprecated( 'WP_Job_Manager_Post_Types::maybe_generate_geolocation_data' );
		$instance = WP_Job_Manager_Post_Types::instance();
		$instance->maybe_generate_geolocation_data( 0, 0, 0, 0 );
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::maybe_add_default_meta_data
	 */
	public function test_maybe_add_default_meta_data() {
		$instance = WP_Job_Manager_Post_Types::instance();
		$post     = wp_insert_post(
			array(
				'post_type'  => 'job_listing',
				'post_title' => 'Hello A',
			)
		);
		delete_post_meta( $post, '_featured' );
		delete_post_meta( $post, '_filled' );
		$this->assertFalse( metadata_exists( 'post', $post, '_filled' ) );
		$this->assertFalse( metadata_exists( 'post', $post, '_featured' ) );
		$instance->maybe_add_default_meta_data( $post, get_post( $post ) );
		$this->assertTrue( metadata_exists( 'post', $post, '_filled' ) );
		$this->assertTrue( metadata_exists( 'post', $post, '_featured' ) );
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::maybe_add_default_meta_data
	 */
	public function test_maybe_add_default_meta_data_non_job_listing() {
		$instance = WP_Job_Manager_Post_Types::instance();
		$post     = wp_insert_post(
			array(
				'post_type'  => 'post',
				'post_title' => 'Hello B',
			)
		);
		delete_post_meta( $post, '_featured' );
		delete_post_meta( $post, '_filled' );
		$this->assertFalse( metadata_exists( 'post', $post, '_filled' ) );
		$this->assertFalse( metadata_exists( 'post', $post, '_featured' ) );
		$instance->maybe_add_default_meta_data( $post, get_post( $post ) );
		$this->assertFalse( metadata_exists( 'post', $post, '_filled' ) );
		$this->assertFalse( metadata_exists( 'post', $post, '_featured' ) );
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::noindex_expired_filled_job_listings
	 */
	public function test_noindex_expired_filled_job_listings() {
		global $wp_query;
		$instance = WP_Job_Manager_Post_Types::instance();
		$job_id   = $this->factory->job_listing->create();
		$post_id  = $this->factory->post->create();

		$jobs = $wp_query = new WP_Query(
			array(
				'p'         => $job_id,
				'post_type' => 'job_listing',
			)
		);
		$this->assertEquals( 1, $jobs->post_count );
		$this->assertTrue( $jobs->is_single );

		while ( $jobs->have_posts() ) {
			$jobs->the_post();
			$post = get_post();
			ob_start();
			$instance->noindex_expired_filled_job_listings();
			$result = ob_get_clean();
			$this->assertEmpty( $result );
		}
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::noindex_expired_filled_job_listings
	 */
	public function test_noindex_expired_filled_job_listings_expired() {
		global $wp_query;
		$instance = WP_Job_Manager_Post_Types::instance();
		$job_id   = $this->factory->job_listing->create( array( 'post_status' => 'expired ' ) );
		$post_id  = $this->factory->post->create();

		$jobs = $wp_query = new WP_Query(
			array(
				'p'         => $job_id,
				'post_type' => 'job_listing',
			)
		);
		$this->assertEquals( 1, $jobs->post_count );
		$this->assertTrue( $jobs->is_single );
		$desired_result = $this->get_wp_no_robots();
		while ( $jobs->have_posts() ) {
			$jobs->the_post();
			$post = get_post();
			ob_start();
			$instance->noindex_expired_filled_job_listings();
			$result = ob_get_clean();
			$this->assertEquals( $desired_result, $result );
		}
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::output_structured_data
	 */
	public function test_output_structured_data() {
		global $wp_query;
		$instance = WP_Job_Manager_Post_Types::instance();
		$job_id   = $this->factory->job_listing->create();
		$post_id  = $this->factory->post->create();

		$jobs = $wp_query = new WP_Query(
			array(
				'p'         => $job_id,
				'post_type' => 'job_listing',
			)
		);
		$this->assertEquals( 1, $jobs->post_count );
		$this->assertTrue( $jobs->is_single );
		while ( $jobs->have_posts() ) {
			$jobs->the_post();
			$post            = get_post();
			$structured_data = wpjm_get_job_listing_structured_data( $post );
			$json_data       = wp_json_encode( $structured_data );
			ob_start();
			$instance->output_structured_data();
			$result = ob_get_clean();
			$this->assertContains( '<script type="application/ld+json">', $result );
			$this->assertContains( $json_data, $result );
		}
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::output_structured_data
	 */
	public function test_output_structured_data_expired() {
		global $wp_query;
		$instance = WP_Job_Manager_Post_Types::instance();
		$job_id   = $this->factory->job_listing->create( array( 'post_status' => 'expired ' ) );
		$post_id  = $this->factory->post->create();

		$jobs = $wp_query = new WP_Query(
			array(
				'p'         => $job_id,
				'post_type' => 'job_listing',
			)
		);
		$this->assertEquals( 1, $jobs->post_count );
		$this->assertTrue( $jobs->is_single );
		while ( $jobs->have_posts() ) {
			$jobs->the_post();
			$post = get_post();
			ob_start();
			$instance->output_structured_data();
			$result = ob_get_clean();
			$this->assertEmpty( $result );
		}
	}

	protected function get_wp_no_robots() {
		ob_start();
		wp_no_robots();
		return ob_get_clean();
	}

	protected function assertNotHasRssItem( $items, $post_id ) {
		$this->assertHasRssItem( $items, $post_id, true );
	}

	protected function assertHasRssItem( $items, $post_id, $not_found = false ) {
		$found = false;
		$guid  = get_the_guid( $post_id );
		$this->assertNotEmpty( $guid );
		foreach ( $items as $item ) {
			foreach ( $item['child'] as $child ) {
				if ( 'guid' === $child['name'] && $guid === $child['content'] ) {
					$found = true;
					break 2;
				}
			}
		}
		if ( ! $not_found ) {
			$this->assertTrue( $found );
		} else {
			$this->assertFalse( $found );
		}
	}

	private function do_job_feed() {
		if ( function_exists( 'header_remove' ) ) {
			header_remove();
		}
		ob_start();
		$instance = WP_Job_Manager_Post_Types::instance();
		try {
			@$instance->job_feed();
			$out = ob_get_clean();
		} catch ( Exception $e ) {
			$out = ob_get_clean();
			throw $e;
		}
		return $out;
	}
}
