<?php

/**
 * @group geocode
 */
class WP_Test_WP_Job_Manager_Geocode extends WPJM_BaseTest {

	public function setUp() {
		parent::setUp();
		add_filter( 'job_manager_geolocation_api_key', array( $this, 'get_google_maps_api_key' ), 10 );
		add_filter( 'job_manager_geolocation_enabled', '__return_true' );
		$this->enable_transport_faker();
	}

	/**
	 * Tests the WP_Job_Manager_Geocode::instance() always returns the same `WP_Job_Manager_Geocode` instance.
	 *
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Geocode::instance
	 */
	public function test_wp_job_manager_api_instance() {
		$instance = WP_Job_Manager_Geocode::instance();
		// check the class
		$this->assertInstanceOf( 'WP_Job_Manager_Geocode', $instance, 'Job Manager Geocode object is instance of WP_Job_Manager_Geocode class' );

		// check it always returns the same object
		$this->assertSame( WP_Job_Manager_Geocode::instance(), $instance, 'WP_Job_Manager_Geocode::instance() must always return the same object' );
	}

	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Geocode::update_location_data
	 * @dataProvider get_location_data
	 */
	public function test_update_location_data( $test_data ) {
		$this->set_expected_responses( $test_data );
		$instance = WP_Job_Manager_Geocode::instance();
		$job_id = $this->factory->job_listing->create();
		$values = array( 'job' => array( 'job_location' => $test_data['location'] ) );
		$instance->update_location_data( $job_id, $values );
		$this->check_test_data( $job_id, $test_data );
	}

	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Geocode::update_location_data
	 */
	public function test_update_location_data_disabled() {
		$test_data = $this->get_valid_location_data();
		$this->set_expected_responses( $test_data );
		$instance = WP_Job_Manager_Geocode::instance();
		$job_id = $this->factory->job_listing->create();
		$values = array( 'job' => array( 'job_location' => $test_data['location'] ) );
		add_filter( 'job_manager_geolocation_enabled', '__return_false' );
		$instance->update_location_data( $job_id, $values );
		add_filter( 'job_manager_geolocation_enabled', '__return_true' );
		$this->check_test_data( $job_id, $this->get_invalid_location_data() );
	}

	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Geocode::update_location_data
	 */
	public function test_update_location_data_not_set() {
		$instance = WP_Job_Manager_Geocode::instance();
		$job_id = $this->factory->job_listing->create();
		$values = array( 'job' => array() );
		$instance->update_location_data( $job_id, $values );
		$this->check_test_data( $job_id, $this->get_invalid_location_data() );
	}

	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Geocode::update_location_data
	 * @dataProvider get_location_data
	 */
	public function test_change_location_data( $test_data ) {
		$other_test_data = $this->get_other_valid_location_data();
		$this->set_expected_responses( $test_data );
		$this->set_expected_responses( $other_test_data );
		$instance = WP_Job_Manager_Geocode::instance();
		$job_id = $this->factory->job_listing->create();

		// Set the initial location data
		$values = array( 'job' => array( 'job_location' => $other_test_data['location'] ) );
		$instance->update_location_data( $job_id, $values );
		$this->check_test_data( $job_id, $other_test_data );

		// Set the new location data and verify that everything is valid
		$instance->change_location_data( $job_id, $test_data['location'] );
		$this->check_test_data( $job_id, $test_data );
	}

	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Geocode::has_location_data
	 */
	public function test_has_location_data() {
		$test_data = $this->get_valid_location_data();
		$this->set_expected_responses( $test_data );
		$instance = WP_Job_Manager_Geocode::instance();
		$job_id = $this->factory->job_listing->create();
		$values = array( 'job' => array( 'job_location' => $test_data['location'] ) );
		$instance->update_location_data( $job_id, $values );
		$this->assertNotEmpty( get_post_meta( $job_id, 'geolocation_city', true ) );
		$this->assertTrue( WP_Job_Manager_Geocode::has_location_data( $job_id ) );
	}

	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Geocode::has_location_data
	 */
	public function test_has_location_data_nope() {
		$job_id = $this->factory->job_listing->create();
		$this->assertEmpty( get_post_meta( $job_id, 'geolocation_city', true ) );
		$this->assertFalse( WP_Job_Manager_Geocode::has_location_data( $job_id ) );
	}

	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Geocode::generate_location_data
	 * @dataProvider get_location_data
	 */
	public function test_generate_location_data( $test_data ) {
		$this->set_expected_responses( $test_data );
		$job_id = $this->factory->job_listing->create();
		WP_Job_Manager_Geocode::generate_location_data( $job_id, $test_data['location'] );
		$this->check_test_data( $job_id, $test_data );
	}

	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Geocode::clear_location_data
	 */
	public function test_clear_location_data() {
		$test_data = $this->get_valid_location_data();
		$this->set_expected_responses( $test_data );
		$job_id = $this->factory->job_listing->create();
		WP_Job_Manager_Geocode::generate_location_data( $job_id, $test_data['location'] );
		$this->check_test_data( $job_id, $test_data );
		WP_Job_Manager_Geocode::clear_location_data( $job_id );
		$this->check_test_data( $job_id, $this->get_invalid_location_data() );
	}

	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Geocode::save_location_data
	 */
	public function test_save_location_data() {
		$job_id = $this->factory->job_listing->create();
		$test_data = array(
			'city' => 'City',
			'country_long' => 'Country Long',
			'country_short' => 'Country',
			'formatted_address' => 'Formatted',
			'lat' => '111',
			'long' => '222',
			'state_long' => 'Washington',
			'state_short' => 'WA',
			'street' => 'Test St.',
			'street_number' => '30',
			'zipcode' => '11111',
			'postcode' => '22222',
		);
		WP_Job_Manager_Geocode::save_location_data( $job_id, $test_data );
		foreach ( $test_data as $key => $value ) {
			$this->assertEquals( $value, get_post_meta( $job_id, 'geolocation_' . $key, true) );
		}
	}

	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Geocode::get_google_maps_api_key
	 */
	public function test_get_google_maps_api_key() {
		$test_key = '_BEST_KEY_EVER_';
		$instance = WP_Job_Manager_Geocode::instance();
		update_option( 'job_manager_google_maps_api_key', $test_key );
		$this->assertEquals( $test_key, $instance->get_google_maps_api_key( '' ) );
		update_option( 'job_manager_google_maps_api_key', '' );
	}

	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Geocode::add_geolocation_endpoint_query_args
	 */
	public function test_add_geolocation_endpoint_query_args() {
		$test_url = 'http://www.example.com/provider';
		$test_location = 'Mars 00000';
		$instance = WP_Job_Manager_Geocode::instance();
		add_filter( 'job_manager_geolocation_api_key', array( $this, 'helper_add_api_key' ) );
		add_filter( 'job_manager_geolocation_region_cctld', array( $this, 'helper_add_region_cctld' ) );
		$result = $instance->add_geolocation_endpoint_query_args( $test_url, $test_location );;
		remove_filter( 'job_manager_geolocation_api_key', array( $this, 'helper_add_api_key' ) );
		remove_filter( 'job_manager_geolocation_region_cctld', array( $this, 'helper_add_region_cctld' ) );
		$this->assertContains( $test_url, $result );
		$this->assertContains( urlencode( $test_location ), $result );
		$this->assertContains( 'key=' .  urlencode( $this->helper_add_api_key('') ), $result );
		$this->assertContains( 'region=' . urlencode( $this->helper_add_region_cctld('') ), $result );
		$locale = get_locale();
		$this->assertContains( 'language=' . urlencode(  substr( $locale, 0, 2 ) ), $result );

	}

	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Geocode::get_location_data
	 * @dataProvider get_location_data
	 */
	public function test_get_location_data_simple_local( $test_data ) {
		$this->set_expected_responses( $test_data );
		$location_data = WP_Job_Manager_Geocode::get_location_data( $test_data['location'] );

		if ( isset( $test_data['expects_location_data'] ) && true === $test_data['expects_location_data'] ) {
			$this->assertNotEmpty( $location_data );
			$this->assertTrue( is_array( $location_data ) );
		} elseif ( isset( $test_data['expects_location_data'] ) && false === $test_data['expects_location_data'] ) {
			$this->assertEmpty( $location_data );
		}
		foreach ( $test_data['location_data'] as $key => $value ) {
			if ( empty( $value ) ) {
				continue;
			}
			$this->assertTrue( isset( $location_data[ $key ] ) );
			$this->assertEquals( $value, $location_data[ $key ] );
		}
	}

	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Geocode::get_location_data
	 * @dataProvider get_location_data
	 * @group google-api
	 */
	public function test_get_location_data_simple_live( $test_data ) {
		$this->use_live_google_api();
		$this->set_expected_responses( $test_data );
		$location_data = WP_Job_Manager_Geocode::get_location_data( $test_data['location'] );
		if ( isset( $test_data['expects_location_data'] ) && true === $test_data['expects_location_data'] ) {
			$this->assertNotEmpty( $location_data );
		} elseif ( isset( $test_data['expects_location_data'] ) && false === $test_data['expects_location_data'] ) {
			$this->assertEmpty( $location_data );
		}
		foreach ( $test_data['location_data'] as $key => $value ) {
			if ( empty( $value ) ) {
				continue;
			}
			$this->assertTrue( is_array( $location_data ) );
			$this->assertTrue( isset( $location_data[ $key ] ) );
			$this->assertEquals( $value, $location_data[ $key ] );
		}
	}

	/**
	 * @since 1.29.1
	 * @covers WP_Job_Manager_Geocode::get_location_data
	 * @group google-api
	 */
	public function test_get_location_data_error_live() {
		$this->use_live_google_api();
		$location_data = WP_Job_Manager_Geocode::get_location_data( 'Fake Moon Town, The Sun 00000' );
		$this->assertTrue( $location_data instanceof  WP_Error );
	}

	/**
	 * Used in `test_add_geolocation_endpoint_query_args()`
	 * @param string $region
	 *
	 * @return string
	 */
	public function helper_add_region_cctld( $region ){
		return 'TESTREGION';
	}

	/**
	 * Used in `test_add_geolocation_endpoint_query_args()`
	 * @param string $key
	 *
	 * @return string
	 */
	public function helper_add_api_key( $key ){
		return 'TESTAPIKEY';
	}

	protected function use_live_google_api() {
		if ( ! getenv( 'WPJM_PHPUNIT_GOOGLE_GEOCODE_API_KEY' ) ) {
			$this->markTestSkipped( 'Geocode test requires WPJM_PHPUNIT_GOOGLE_GEOCODE_API_KEY environment variable to be set to valid Google Maps Geocode API Key' );
		}
		$this->disable_transport_faker();
	}

	public function get_google_maps_api_key() {
		if ( ! getenv( 'WPJM_PHPUNIT_GOOGLE_GEOCODE_API_KEY' ) ) {
			$this->markTestSkipped( 'Geocode test requires WPJM_PHPUNIT_GOOGLE_GEOCODE_API_KEY environment variable to be set to valid Google Maps Geocode API Key' );
		}
		return getenv( 'WPJM_PHPUNIT_GOOGLE_GEOCODE_API_KEY' );
	}

	protected function check_test_data( $job_id, $test_data ) {
		$instance = WP_Job_Manager_Geocode::instance();
		if ( isset( $test_data['expects_location_data'] ) && true === $test_data['expects_location_data'] ) {
			$this->assertTrue( $instance->has_location_data( $job_id ) );
		} elseif ( isset( $test_data['expects_location_data'] ) && false === $test_data['expects_location_data'] ) {
			$this->assertFalse( $instance->has_location_data( $job_id ) );
		}
		foreach ( $test_data['location_data'] as $key => $expected_value ) {
			$this->assertEquals( $expected_value, get_post_meta( $job_id, 'geolocation_' . $key, true ) );
		}
	}

	protected function set_expected_responses( $test_data ) {
		if ( isset( $test_data['fake_response'] ) ) {
			$transport = $this->get_request_transport();
			if ( ! isset( $test_data['fake_response']['request'] ) ) {
				$test_data['fake_response']['request'] = array( 'url' => $this->build_geocode_url( $test_data['location'] ) );
			}
			if ( ! isset( $test_data['fake_response']['response'] ) && isset( $test_data['fake_response']['data'] ) ) {
				$test_data['fake_response']['response'] = array(
					'results' => array(
						array(
							"address_components" => $test_data['fake_response']['data'],
							"formatted_address" => $test_data["location"],
							'geometry' => array(
								'location' => array(
									'lat' => 0,
									'lng' => 0,
								)
							),
						),
					),
					'status' => 'OK'
				);
			}
			$transport->add_fake_request( $test_data['fake_response']['request'], array( 'body' => $test_data['fake_response']['response'] ) );
		}
	}

	public function get_location_data() {
		return array(
			array( $this->get_invalid_location_data() ),
			array( $this->get_valid_location_data() ),
		);
	}

	protected function get_invalid_location_data() {
		return array(
			'location' => '',
			'expects_location_data' => false,
			'location_data' => array(
				'city' => '',
				'country_long' => '',
				'country_short' => '',
				'formatted_address' => '',
				'lat' => '',
				'long' => '',
				'state_long' => '',
				'state_short' => '',
				'street' => '',
				'street_number' => '',
				'zipcode' => '',
				'postcode' => '',
			),
		);
	}

	private function build_geocode_url( $location ) {
		$invalid_chars = array( " " => "+", "," => "", "?" => "", "&" => "", "=" => "" , "#" => "" );
		$location   = trim( strtolower( str_replace( array_keys( $invalid_chars ), array_values( $invalid_chars ), $location ) ) );
		return apply_filters( 'job_manager_geolocation_endpoint', WP_Job_Manager_Geocode::GOOGLE_MAPS_GEOCODE_API_URL, $location );
	}

	protected function get_valid_location_data() {
		return array(
			'location' => 'Seattle, WA, USA',
			'expects_location_data' => true,
			'location_data' => array(
				'city' => 'Seattle',
			),
			'fake_response' => array(
				'data' => array(
					array(
						"short_name" => "Seattle",
						"long_name" => "Seattle",
						"types" => array( "locality", "political" ),
					),
					array(
						"short_name" => "WA",
						"long_name" => "Washington",
						"types" => array( "administrative_area_level_1", "political" ),
					),
					array(
						"short_name" => "US",
						"long_name" => "United States",
						"types" => array( "country", "political" ),
					),
				),
			),
		);
	}

	protected function get_other_valid_location_data() {
		return array(
			'location' => 'Chicago, IL, USA',
			'expects_location_data' => true,
			'location_data' => array(
				'city' => 'Chicago',
			),
			'fake_response' => array(
				'data' => array(
					array(
						"short_name" => "Chicago",
						"long_name" => "Chicago",
						"types" => array( "locality", "political" ),
					),
					array(
						"short_name" => "IL",
						"long_name" => "Illinois",
						"types" => array( "administrative_area_level_1", "political" ),
					),
					array(
						"short_name" => "US",
						"long_name" => "United States",
						"types" => array( "country", "political" ),
					),
				),
			),
		);
	}
}
