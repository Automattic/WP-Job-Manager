<?php

class WP_Test_WP_Job_Manager_Post_Types extends WPJM_BaseTest {
	public function setUp(): void {
		parent::setUp();
		$this->enable_manage_job_listings_cap();
		update_option( 'job_manager_enable_categories', 1 );
		update_option( 'job_manager_enable_types', 1 );
		$this->reregister_post_type();
		add_filter( 'job_manager_geolocation_enabled', '__return_false' );
	}

	public function tearDown(): void {
		parent::tearDown();
		add_filter( 'job_manager_geolocation_enabled', '__return_true' );
	}

	/**
	 * @since 1.33.0
	 * @covers WP_Job_Manager_Post_Types::output_kses_post
	 */
	public function test_output_kses_post_simple() {
		$job_id = $this->factory->job_listing->create(
			[
			'post_content' => '<p>This is a simple job listing</p>',
			]
		);

		$test_content = wpjm_get_the_job_description( $job_id );

		ob_start();
		WP_Job_Manager_Post_Types::output_kses_post( $test_content );
		$actual_content = ob_get_clean();

		$this->assertEquals( $test_content, $actual_content, 'No HTML should have been removed from this test.' );
	}

	/**
	 * @since 1.33.0
	 * @covers WP_Job_Manager_Post_Types::output_kses_post
	 */
	public function test_output_kses_post_allow_embeds() {
		$job_id = $this->factory->job_listing->create(
			[
			'post_content' => '<p>This is a simple job listing</p><p>https://www.youtube.com/watch?v=S_GVbuddri8</p>',
			]
		);

		$test_content = wpjm_get_the_job_description( $job_id );

		ob_start();
		WP_Job_Manager_Post_Types::output_kses_post( $test_content );
		$actual_content = ob_get_clean();

		$this->assertFalse( strpos( $actual_content, '<p>https://www.youtube.com/watch?v=S_GVbuddri8</p>' ), 'The YouTube link should have been expanded to an iframe' );
		$this->assertGreaterThan( 0, strpos( $actual_content, '<iframe ' ), 'The iframe should not have been filtered out' );
		$this->assertGreaterThan( 0, strpos( $actual_content, 'src="https://www.youtube.com' ), 'The iframe source should not have been filtered out' );
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
			[
				'p'         => $job_id,
				'post_type' => 'job_listing',
			]
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
			$this->assertStringContainsString( '<div class="single_job_listing"', $post_content_filtered );

			ob_start();
			the_content();
			$post_content_filtered = ob_get_clean();
			$this->assertNotEquals( $post->post_content, $post_content_filtered );
			$this->assertStringContainsString( '<div class="single_job_listing"', $post_content_filtered );
			$this->assertStringContainsString( $post->post_content, $post_content_filtered );
		}
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::job_feed
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
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
	 * @preserveGlobalState disabled
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
	 * @preserveGlobalState disabled
	 */
	public function test_job_feed_location_search() {
		$this->factory->job_listing->create_many(
			5,
			[
				'meta_input' => [
					'_job_location' => 'Portland, OR, USA',
				],
			]
		);
		$seattle_job_id = $this->factory->job_listing->create(
			[
				'meta_input' => [
					'_job_location' => 'Seattle, WA, USA',
				],
			]
		);
		$chicago_job_id = $this->factory->job_listing->create(
			[
				'meta_input' => [
					'_job_location' => 'Chicago, IL, USA',
				],
			]
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
	 * @preserveGlobalState disabled
	 */
	public function test_job_feed_keyword_search() {
		$this->factory->job_listing->create_many( 3 );
		$dog_job_id  = $this->factory->job_listing->create(
			[
				'post_title' => 'Dog Whisperer',
			]
		);
		$dino_job_id = $this->factory->job_listing->create(
			[
				'post_title' => 'Dinosaur Whisperer Pro',
			]
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
		$new_jobs       = [];
		$type_a         = wp_create_term( 'Job Type A', 'job_listing_type' );
		$type_b         = wp_create_term( 'Job Type B', 'job_listing_type' );
		$new_job_args   = [];
		$new_job_args[] = [
			'meta_input' => [
				'_company_name' => 'Custom Company A',
			],
			'tax_input'  => [
				'job_listing_type' => $type_a['term_id'],
			],
		];
		$new_job_args[] = [
			'meta_input' => [
				'_job_location' => 'Custom Location B',
				'_company_name' => '',
			],
			'tax_input'  => [
				'job_listing_type' => $type_b['term_id'],
			],
		];
		$new_job_args[] = [
			'meta_input' => [
				'_job_location' => 'Custom Location A',
				'_company_name' => 'Custom Company B',
			],
			'tax_input'  => [],
		];
		$new_jobs[]     = $this->factory->job_listing->create( $new_job_args[0] );
		$new_jobs[]     = $this->factory->job_listing->create( $new_job_args[1] );
		$new_jobs[]     = $this->factory->job_listing->create( $new_job_args[2] );
		$jobs           = $wp_query = new WP_Query(
			[
				'post_type' => 'job_listing',
				'orderby'   => 'ID',
				'order'     => 'ASC',
			]
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
				$this->assertStringContainsString( 'job_listing:location', $result );
				$this->assertStringContainsString( $job_location, $result );
			} else {
				$this->assertStringNotContainsString( 'job_listing:location', $result );
			}

			if ( $has_job_type ) {
				$job_type = current( wpjm_get_the_job_types( $post ) );
				$this->assertStringContainsString( 'job_listing:job_type', $result );
				$this->assertStringContainsString( $job_type->name, $result );
			} else {
				$this->assertStringNotContainsString( 'job_listing:job_type', $result );
			}

			if ( $has_company ) {
				$company_name = get_the_company_name( $post );
				$this->assertStringContainsString( $company_name, $result );
				$this->assertStringContainsString( 'job_listing:company', $result );
			} else {
				$this->assertStringNotContainsString( 'job_listing:company', $result );
			}
		}
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::check_for_expired_jobs
	 */
	public function test_check_for_expired_jobs() {
		$new_jobs                 = [];
		$new_jobs['none']        = $this->factory->job_listing->create();
		delete_post_meta( $new_jobs['none'], '_job_expires' );
		$new_jobs['empty']         = $this->factory->job_listing->create();
		update_post_meta( $new_jobs['empty'], '_job_expires', '' );
		$new_jobs['invalid-none'] = $this->factory->job_listing->create( [ 'meta_input' => [ '_job_expires' => '0000-00-00' ] ] );
		$new_jobs['today']        = $this->factory->job_listing->create( [ 'meta_input' => [ '_job_expires' => wp_date( 'Y-m-d' ) ] ] );
		$new_jobs['yesterday']    = $this->factory->job_listing->create( [ 'meta_input' => [ '_job_expires' => wp_date( 'Y-m-d', strtotime( '-1 day' ) ) ] ] );
		$new_jobs['ancient']      = $this->factory->job_listing->create( [ 'meta_input' => [ '_job_expires' => wp_date( 'Y-m-d', strtotime( '-100 day' ) ) ] ] );
		$new_jobs['tomorrow']     = $this->factory->job_listing->create( [ 'meta_input' => [ '_job_expires' => wp_date( 'Y-m-d', strtotime( '+1 day' ) ) ] ] );
		$new_jobs['30daysago']    = $this->factory->job_listing->create( [ 'meta_input' => [ '_job_expires' => wp_date( 'Y-m-d', strtotime( '-30 day' ) ) ] ] );
		$new_jobs['31daysago']    = $this->factory->job_listing->create( [ 'meta_input' => [ '_job_expires' => wp_date( 'Y-m-d', strtotime( '-31 day' ) ) ] ] );

		$instance = WP_Job_Manager_Post_Types::instance();
		$this->assertNotExpired( $new_jobs['none'] );
		$this->assertNotExpired( $new_jobs['empty'] );
		$this->assertNotExpired( $new_jobs['invalid-none'] );
		$this->assertNotExpired( $new_jobs['yesterday'] );
		$this->assertNotExpired( $new_jobs['today'] );
		$this->assertNotExpired( $new_jobs['ancient'] );
		$this->assertNotExpired( $new_jobs['tomorrow'] );
		$instance->check_for_expired_jobs();
		$this->assertNotExpired( $new_jobs['none'] );
		$this->assertNotExpired( $new_jobs['empty'] );
		$this->assertNotExpired( $new_jobs['invalid-none'] );
		$this->assertNotExpired( $new_jobs['today'] );
		$this->assertExpired( $new_jobs['yesterday'] );
		$this->assertExpired( $new_jobs['ancient'] );
		$this->assertNotExpired( $new_jobs['tomorrow'] );

		$this->factory->job_listing->set_post_age( $new_jobs['ancient'], '-100 days' );
		$this->factory->job_listing->set_post_age( $new_jobs['yesterday'], '-1 day' );
		$this->factory->job_listing->set_post_age( $new_jobs['30daysago'], '-30 days' );
		$this->factory->job_listing->set_post_age( $new_jobs['31daysago'], '-31 days' );
		$this->factory->job_listing->set_post_age( $new_jobs['tomorrow'], '+1 day' );

		$instance->check_for_expired_jobs();
		$this->assertNotTrashed( $new_jobs['ancient'] );

		add_filter( 'job_manager_delete_expired_jobs', '__return_true' );
		$instance->check_for_expired_jobs();
		remove_filter( 'job_manager_delete_expired_jobs', '__return_true' );

		$this->assertTrashed( $new_jobs['ancient'] );
		$this->assertTrashed( $new_jobs['31daysago'] );
		$this->assertNotTrashed( $new_jobs['yesterday'] );
		$this->assertNotTrashed( $new_jobs['30daysago'] );
		$this->assertNotTrashed( $new_jobs['today'] );
		$this->assertNotTrashed( $new_jobs['tomorrow'] );
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::check_for_expired_jobs
	 */
	public function test_check_for_expired_jobs_time_of_day_variations() {
		$today = $this->factory->job_listing->create( [ 'meta_input' => [ '_job_expires' => wp_date( 'Y-m-d' ) ] ] );
		$instance = WP_Job_Manager_Post_Types::instance();
		$this->assertNotExpired( $today );

		remove_all_filters( 'job_manager_jobs_expire_end_of_day' );
		add_filter( 'job_manager_jobs_expire_end_of_day', '__return_true' );
		$instance->check_for_expired_jobs();
		$this->assertNotExpired( $today );

		remove_all_filters( 'job_manager_jobs_expire_end_of_day' );
		add_filter( 'job_manager_jobs_expire_end_of_day', '__return_false' );
		$instance->check_for_expired_jobs();
		$this->assertExpired( $today );
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::delete_old_previews
	 */
	public function test_delete_old_previews() {
		$new_jobs              = [];
		$new_jobs['now']       = $this->factory->job_listing->create( [ 'post_status' => 'preview' ] );
		$new_jobs['yesterday'] = $this->factory->job_listing->create(
			[
				'post_status' => 'preview',
				'age'         => '-1 day',
			]
		);
		$new_jobs['29days']    = $this->factory->job_listing->create(
			[
				'post_status' => 'preview',
				'age'         => '-29 days',
			]
		);
		$new_jobs['30days']    = $this->factory->job_listing->create(
			[
				'post_status' => 'preview',
				'age'         => '-30 days',
			]
		);
		$new_jobs['31days']    = $this->factory->job_listing->create(
			[
				'post_status' => 'preview',
				'age'         => '-31 days',
			]
		);
		$new_jobs['60days']    = $this->factory->job_listing->create(
			[
				'post_status' => 'preview',
				'age'         => '-60 days',
			]
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
		$_POST['_job_expires'] = $expire_date = wp_date( 'Y-m-d', strtotime( '+10 days', current_datetime()->getTimestamp() ) );
		$instance->set_expiry( $post );
		unset( $_POST['_job_expires'] );
		$this->assertEquals( $expire_date, get_post_meta( $post->ID, '_job_expires', true ) );
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::set_expiry
	 */
	public function test_set_expiry_calculate() {
		$post             = get_post( $this->factory->job_listing->create( [ 'meta_input' => [ '_job_duration' => 77 ] ] ) );
		$instance         = WP_Job_Manager_Post_Types::instance();
		$expire_date      = wp_date( 'Y-m-d', strtotime( '+77 days', current_datetime()->getTimestamp() ) );
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
		$post     = get_post( $this->factory->job_listing->create( [ 'meta_input' => [ '_job_expires' => '2008-01-01' ] ] ) );
		$instance = WP_Job_Manager_Post_Types::instance();
		$instance->set_expiry( $post );
		$this->assertEquals( '', get_post_meta( $post->ID, '_job_expires', true ) );
	}

	/**
	 * Time zones to test certain expiry tests with.
	 *
	 * @return string[][]
	 */
	public function data_provider_timezones() {
		return [
			'UTC'                 => [ 'UTC' ],
			'Australia/Melbourne' => [ 'Australia/Melbourne' ],
			'America/Los_Angeles' => [ 'America/Los_Angeles' ],
			'Pacific/Honolulu'    => [ 'Pacific/Honolulu' ],
		];
	}

	/**
	 * @since 1.35.0
	 * @dataProvider data_provider_timezones
	 * @param string $tz Time zone.
	 */
	public function test_has_job_expired_past( $tz ) {
		update_option( 'timezone_string', $tz );

		$job_id = $this->factory->job_listing->create( [ 'meta_input' => [ '_job_expires' => current_datetime()->sub( new DateInterval( 'P1D' ) )->format( 'Y-m-d' ) ], ] );

		$this->assertTrue( WP_Job_Manager_Post_Types::instance()->has_job_expired( $job_id ) );
	}

	/**
	 * @since 1.35.0
	 * @dataProvider data_provider_timezones
	 * @param string $tz Time zone.
	 */
	public function test_has_job_expired_same_day_start_of_day( $tz ) {
		update_option( 'timezone_string', $tz );

		remove_all_filters( 'job_manager_jobs_expire_end_of_day' );
		add_filter( 'job_manager_jobs_expire_end_of_day', '__return_false' );

		$job_id = $this->factory->job_listing->create( [ 'meta_input' => [ '_job_expires' => current_datetime()->format( 'Y-m-d' ) ], ] );
		$result = WP_Job_Manager_Post_Types::instance()->has_job_expired( $job_id );
		remove_all_filters( 'job_manager_jobs_expire_end_of_day' );

		$this->assertTrue( $result );
	}

	/**
	 * @since 1.35.0
	 * @dataProvider data_provider_timezones
	 * @param string $tz Time zone.
	 */
	public function test_has_job_expired_same_day_end_of_day( $tz ) {
		update_option( 'timezone_string', $tz );

		remove_all_filters( 'job_manager_jobs_expire_end_of_day' );
		add_filter( 'job_manager_jobs_expire_end_of_day', '__return_true' );

		$job_id = $this->factory->job_listing->create( [ 'meta_input' => [ '_job_expires' => current_datetime()->format( 'Y-m-d' ) ], ] );
		$result = WP_Job_Manager_Post_Types::instance()->has_job_expired( $job_id );
		remove_all_filters( 'job_manager_jobs_expire_end_of_day' );

		$this->assertFalse( $result );
	}

	/**
	 * @since 1.35.0
	 * @dataProvider data_provider_timezones
	 * @param string $tz Time zone.
	 */
	public function test_has_job_expired_tomorrow( $tz ) {
		update_option( 'timezone_string', $tz );

		$job_id = $this->factory->job_listing->create( [ 'meta_input' => [ '_job_expires' => current_datetime()->add( new DateInterval( 'P1D' ) )->format( 'Y-m-d' ) ], ] );
		$result = WP_Job_Manager_Post_Types::instance()->has_job_expired( $job_id );

		$this->assertFalse( $result );
	}

	/**
	 * @since 1.35.0
	 * @dataProvider data_provider_timezones
	 * @param string $tz Time zone.
	 */
	public function test_get_job_expiration( $tz ) {
		update_option( 'timezone_string', $tz );

		$test_date = '2020-01-01';

		$job_id = $this->factory->job_listing->create( [ 'meta_input' => [ '_job_expires' => $test_date ] ] );
		$result = WP_Job_Manager_Post_Types::instance()->get_job_expiration( $job_id );

		$this->assertEquals( $test_date, $result->format( 'Y-m-d' ) );
	}

	/**
	 * @since 1.35.0
	 * @param string $tz Time zone.
	 */
	public function test_get_job_expiration_null() {
		$job_id = $this->factory->job_listing->create( [ 'meta_input' => [ '_job_expires' => '' ] ] );
		$result = WP_Job_Manager_Post_Types::instance()->get_job_expiration( $job_id );

		$this->assertFalse( $result );
	}

	/**
	 * @since 1.35.0
	 * @param string $tz Time zone.
	 */
	public function test_get_job_expiration_start_of_day() {
		remove_all_filters( 'job_manager_jobs_expire_end_of_day' );
		add_filter( 'job_manager_jobs_expire_end_of_day', '__return_false' );

		$job_id = $this->factory->job_listing->create( [ 'meta_input' => [ '_job_expires' => '2020-01-01' ] ] );
		$result = WP_Job_Manager_Post_Types::instance()->get_job_expiration( $job_id );

		remove_all_filters( 'job_manager_jobs_expire_end_of_day' );

		$this->assertEquals( '00:00:00', $result->format( 'H:i:s' ) );
	}

	/**
	 * @since 1.35.0
	 * @param string $tz Time zone.
	 */
	public function test_get_job_expiration_end_of_day() {
		remove_all_filters( 'job_manager_jobs_expire_end_of_day' );
		add_filter( 'job_manager_jobs_expire_end_of_day', '__return_true' );

		$job_id = $this->factory->job_listing->create( [ 'meta_input' => [ '_job_expires' => '2020-01-01' ] ] );
		$result = WP_Job_Manager_Post_Types::instance()->get_job_expiration( $job_id );

		remove_all_filters( 'job_manager_jobs_expire_end_of_day' );

		$this->assertEquals( '23:59:59', $result->format( 'H:i:s' ) );
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::fix_post_name
	 */
	public function test_fix_post_name() {
		$instance = WP_Job_Manager_Post_Types::instance();
		// Legit.
		$data                 = [
			'post_type'   => 'job_listing',
			'post_status' => 'pending',
			'post_name'   => 'Bad ABC',
		];
		$postarr              = [];
		$postarr['post_name'] = 'TEST 123';
		$data_fixed           = $instance->fix_post_name( $data, $postarr );
		$this->assertEquals( $postarr['post_name'], $data_fixed['post_name'] );

		// Bad Post Type.
		$data                 = [
			'post_type'   => 'post',
			'post_status' => 'pending',
			'post_name'   => 'Bad ABC',
		];
		$postarr              = [];
		$postarr['post_name'] = 'TEST 123';
		$data_fixed           = $instance->fix_post_name( $data, $postarr );
		$this->assertEquals( $data['post_name'], $data_fixed['post_name'] );

		// Bad Post Status.
		$data                 = [
			'post_type'   => 'job_listing',
			'post_status' => 'publish',
			'post_name'   => 'Bad ABC',
		];
		$postarr              = [];
		$postarr['post_name'] = 'TEST 123';
		$data_fixed           = $instance->fix_post_name( $data, $postarr );
		$this->assertEquals( $data['post_name'], $data_fixed['post_name'] );
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::maybe_add_geolocation_data
	 */
	public function test_get_permalink_structure() {
		$permalink_test = [
			'job_base'      => 'job-test-a',
			'category_base' => 'job-cat-b',
			'type_base'     => 'job-type-c',
		];
		update_option( WP_Job_Manager_Post_Types::PERMALINK_OPTION_NAME, wp_json_encode( $permalink_test ) );
		$permalinks = WP_Job_Manager_Post_Types::get_permalink_structure();
		delete_option( WP_Job_Manager_Post_Types::PERMALINK_OPTION_NAME );
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
				[
					'menu_order' => 10,
					'meta_input' => [ '_featured' => 0 ],
				]
			)
		);

		$post = get_post(
			$this->factory->job_listing->create(
				[
					'menu_order' => 10,
					'meta_input' => [ '_featured' => 0 ],
				]
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
				[
					'menu_order' => 10,
					'meta_input' => [ '_featured' => 0 ],
				]
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
				[
					'menu_order' => 10,
					'meta_input' => [ '_featured' => 0 ],
				]
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
			[
				'post_type'  => 'job_listing',
				'post_title' => 'Hello A',
			]
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
			[
				'post_type'  => 'post',
				'post_title' => 'Hello B',
			]
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
			[
				'p'         => $job_id,
				'post_type' => 'job_listing',
			]
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
		$job_id   = $this->factory->job_listing->create( [ 'post_status' => 'expired ' ] );
		$post_id  = $this->factory->post->create();

		$jobs = $wp_query = new WP_Query(
			[
				'p'         => $job_id,
				'post_type' => 'job_listing',
			]
		);
		$this->assertEquals( 1, $jobs->post_count );
		$this->assertTrue( $jobs->is_single );
		while ( $jobs->have_posts() ) {
			$jobs->the_post();

			if ( function_exists('wp_robots') ) {
				$instance->noindex_expired_filled_job_listings();
				ob_start();
				$wp_robots = wp_robots();
				$result = ob_get_clean();
				$this->assertNotFalse( strpos( $result, 'noindex' ) );
			} else {
				$desired_result = $this->get_wp_no_robots();
				$post = get_post();
				ob_start();
				$instance->noindex_expired_filled_job_listings();
				$result = ob_get_clean();
				$this->assertEquals( $desired_result, $result );
			}
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
			[
				'p'         => $job_id,
				'post_type' => 'job_listing',
			]
		);
		$this->assertEquals( 1, $jobs->post_count );
		$this->assertTrue( $jobs->is_single );
		while ( $jobs->have_posts() ) {
			$jobs->the_post();
			$post            = get_post();
			$structured_data = wpjm_get_job_listing_structured_data( $post );
			$json_data       = wpjm_esc_json( wp_json_encode( $structured_data ), true );
			ob_start();
			$instance->output_structured_data();
			$result = ob_get_clean();
			$this->assertStringContainsString( '<script type="application/ld+json">', $result );
			$this->assertStringContainsString( $json_data, $result );
		}
	}

	/**
	 * @since 1.28.0
	 * @covers WP_Job_Manager_Post_Types::output_structured_data
	 */
	public function test_output_structured_data_expired() {
		global $wp_query;
		$instance = WP_Job_Manager_Post_Types::instance();
		$job_id   = $this->factory->job_listing->create( [ 'post_status' => 'expired ' ] );
		$post_id  = $this->factory->post->create();

		$jobs = $wp_query = new WP_Query(
			[
				'p'         => $job_id,
				'post_type' => 'job_listing',
			]
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

	/**
	 * @covers WP_Job_Manager_Post_Types::sanitize_meta_field_based_on_input_type
	 */
	public function test_sanitize_meta_field_based_on_input_type_text() {
		$strings = [
			[
				'expected' => 'This is a test.',
				'test'     => 'This is a test. <script>alert("bad");</script>',
			],
			[
				'expected' => 0,
				'test'     => 0,
			],
			[
				'expected' => '',
				'test'     => false,
			],
			[
				'expected' => '',
				'test'     => '%AB%BC%DE',
			],
			[
				'expected' => 'САПР',
				'test'     => 'САПР',
			],
			[
				'expected' => 'Standard String',
				'test'     => 'Standard String',
			],
			[
				'expected' => 'My iframe:',
				'test'     => 'My iframe: <iframe src="http://example.com"></iframe>',
			],
		];

		$this->set_up_custom_job_listing_data_feilds();
		$results = [];
		foreach ( $strings as $str ) {
			$results[] = [
				'expected' => $str['expected'],
				'result'   =>  WP_Job_Manager_Post_Types::sanitize_meta_field_based_on_input_type( $str['test'], '_text' ),
			];
		}

		foreach ( $results as $result ) {
			$this->assertEquals( $result['expected'], $result['result'] );
		}
	}

	/**
	 * @covers WP_Job_Manager_Post_Types::sanitize_meta_field_based_on_input_type
	 */
	public function test_sanitize_meta_field_based_on_input_type_textarea() {
		$strings = [
			[
				'expected' => 'This is a test. alert("bad");',
				'test'     => 'This is a test. <script>alert("bad");</script>',
			],
			[
				'expected' => 0,
				'test'     => 0,
			],
			[
				'expected' => '',
				'test'     => false,
			],
			[
				'expected' => '%AB%BC%DE',
				'test'     => '%AB%BC%DE',
			],
			[
				'expected' => 'САПР',
				'test'     => 'САПР',
			],
			[
				'expected' => 'Standard String',
				'test'     => 'Standard String',
			],
			[
				'expected' => 'My iframe: ',
				'test'     => 'My iframe: <iframe src="http://example.com"></iframe>',
			],
		];

		$this->set_up_custom_job_listing_data_feilds();
		$results = [];
		foreach ( $strings as $str ) {
			$results[] = [
				'expected' => $str['expected'],
				'result'   =>  WP_Job_Manager_Post_Types::sanitize_meta_field_based_on_input_type( $str['test'], '_textarea' ),
			];
		}

		foreach ( $results as $result ) {
			$this->assertEquals( $result['expected'], $result['result'] );
		}
	}

	/**
	 * @covers WP_Job_Manager_Post_Types::sanitize_meta_field_based_on_input_type
	 */
	public function test_sanitize_meta_field_based_on_input_type_checkbox() {
		$strings = [
			[
				'expected' => 1,
				'test'     => 'false',
			],
			[
				'expected' => 0,
				'test'     => '',
			],
			[
				'expected' => 0,
				'test'     => false,
			],
			[
				'expected' => 1,
				'test'     => true,
			],
		];

		$this->set_up_custom_job_listing_data_feilds();
		$results = [];
		foreach ( $strings as $str ) {
			$results[] = [
				'expected' => $str['expected'],
				'result'   =>  WP_Job_Manager_Post_Types::sanitize_meta_field_based_on_input_type( $str['test'], '_checkbox' ),
			];
		}
		$this->remove_custom_job_listing_data_feilds();

		foreach ( $results as $result ) {
			$this->assertEquals( $result['expected'], $result['result'] );
		}
	}

	/**
	 * @covers WP_Job_Manager_Post_Types::sanitize_meta_field_application
	 */
	public function test_sanitize_meta_field_application() {
		$strings = [
			[
				'expected' => 'http://test%20email@example.com',
				'test'     => 'test email@example.com',
			],
			[
				'expected' => 'http://awesome',
				'test'     => 'awesome',
			],
			[
				'expected' => 'https://example.com',
				'test'     => 'https://example.com',
			],
			[
				'expected' => 'example@example.com',
				'test'     => 'example@example.com',
			],
		];

		$this->set_up_custom_job_listing_data_feilds();
		$results = [];
		foreach ( $strings as $str ) {
			$results[] = [
				'expected' => $str['expected'],
				'result'   =>  WP_Job_Manager_Post_Types::sanitize_meta_field_application( $str['test'], '_application' ),
			];
		}
		$this->remove_custom_job_listing_data_feilds();

		foreach ( $results as $result ) {
			$this->assertEquals( $result['expected'], $result['result'] );
		}
	}

	/**
	 * @covers WP_Job_Manager_Post_Types::sanitize_meta_field_url
	 */
	public function test_sanitize_meta_field_url() {
		$strings = [
			[
				'expected' => 'http://example.com',
				'test'     => 'http://example.com',
			],
			[
				'expected' => '',
				'test'     => 'slack://custom-url',
			],
			[
				'expected' => 'http://example.com',
				'test'     => 'example.com',
			],
			[
				'expected' => 'http://example.com/?baz=bar&foo%5Bbar%5D=baz',
				'test'     => 'http://example.com/?baz=bar&foo[bar]=baz',
			],
		];

		$this->set_up_custom_job_listing_data_feilds();
		$results = [];
		foreach ( $strings as $str ) {
			$results[] = [
				'expected' => $str['expected'],
				'result'   =>  WP_Job_Manager_Post_Types::sanitize_meta_field_url( $str['test'] ),
			];
		}
		$this->remove_custom_job_listing_data_feilds();

		foreach ( $results as $result ) {
			$this->assertEquals( $result['expected'], $result['result'] );
		}
	}

	/**
	 * Data provider for \WP_Test_WP_Job_Manager_Post_Types::test_sanitize_meta_field_date.
	 *
	 * @return string[][]
	 */
	public function data_provider_sanitize_meta_field_date() {
		return [
			'invalid-not-date'          => [
				'',
				'http://example.com',
			],
			'invalid-bad-date-format'   => [
				'',
				'January 1, 2019',
			],
			'invalid-bad-date-format-2' => [
				'',
				'01-01-2019',
			],
			'valid-date-format'         => [
				'2019-01-01',
				'2019-01-01',
			],
			'valid-date-format-tz-1'    => [
				'2019-01-01',
				'2019-01-01',
				'Australia/Melbourne',
			],
			'valid-date-format-tz-2'    => [
				'2019-01-01',
				'2019-01-01',
				'America/Los_Angeles',
			],
			'valid-date-format-tz-3'    => [
				'2019-01-01',
				'2019-01-01',
				'Pacific/Honolulu',
			],
		];
	}

	/**
	 * @covers WP_Job_Manager_Post_Types::sanitize_meta_field_date
	 * @dataProvider data_provider_sanitize_meta_field_date
	 */
	public function test_sanitize_meta_field_date( $expected, $test, $tz = null ) {
		if ( $tz ) {
			update_option( 'timezone_string', $tz );
		}

		$this->set_up_custom_job_listing_data_feilds();
		$result = WP_Job_Manager_Post_Types::sanitize_meta_field_date( $test );
		$this->remove_custom_job_listing_data_feilds();

		$this->assertEquals( $expected, $result );
	}

	private function set_up_custom_job_listing_data_feilds() {
		add_filter( 'job_manager_job_listing_data_fields', [ $this, 'custom_job_listing_data_fields' ] );
	}

	private function remove_custom_job_listing_data_feilds() {
		remove_filter( 'job_manager_job_listing_data_fields', [ $this, 'custom_job_listing_data_fields' ] );
	}

	public function custom_job_listing_data_fields() {
		return [
			'_text'    => [
				'label'         => 'Text Field',
				'placeholder'   => 'Text Field',
				'description'   => 'Text Field',
				'priority'      => 1,
				'type'          => 'text',
				'data_type'     => 'string',
				'show_in_admin' => true,
				'show_in_rest'  => true,
			],
			'_textarea'    => [
				'label'         => 'Textarea Field',
				'placeholder'   => 'Textarea Field',
				'description'   => 'Textarea Field',
				'priority'      => 1,
				'type'          => 'textarea',
				'data_type'     => 'string',
				'show_in_admin' => true,
				'show_in_rest'  => true,
			],
			'_url'    => [
				'label'         => 'URL Field',
				'placeholder'   => 'URL Field',
				'description'   => 'URL Field',
				'priority'      => 1,
				'type'          => 'text',
				'data_type'     => 'string',
				'show_in_admin' => true,
				'show_in_rest'  => true,
				'sanitize_callback' => [ 'WP_Job_Manager_Post_Types', 'sanitize_meta_field_url' ],
			],
			'_checkbox'    => [
				'label'         => 'Checkbox Field',
				'placeholder'   => 'Checkbox Field',
				'description'   => 'Checkbox Field',
				'priority'      => 1,
				'type'          => 'checkbox',
				'data_type'     => 'integer',
				'show_in_admin' => true,
				'show_in_rest'  => true,
			],
			'_date'    => [
				'label'             => 'Checkbox Field',
				'placeholder'       => 'Checkbox Field',
				'description'       => 'Checkbox Field',
				'priority'          => 1,
				'show_in_admin'     => true,
				'show_in_rest'      => true,
				'classes'           => [ 'job-manager-datepicker' ],
				'sanitize_callback' => [ 'WP_Job_Manager_Post_Types', 'sanitize_meta_field_date' ],
			],
			'_application'    => [
				'label'             => 'Application Field',
				'placeholder'       => 'Application Field',
				'description'       => 'Application Field',
				'priority'          => 1,
				'show_in_admin'     => true,
				'show_in_rest'      => true,
				'sanitize_callback' => [ 'WP_Job_Manager_Post_Types', 'sanitize_meta_field_application' ],
			],
		];
	}
}
