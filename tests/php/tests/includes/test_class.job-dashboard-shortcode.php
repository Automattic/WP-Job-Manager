<?php

namespace WP_Job_Manager;

/**
 * @group job-dashboard
 */
class WP_Test_Job_Dashboard_Shortcode extends \WPJM_BaseTest {

	public function tearDown(): void {
		unset( $_GET['view'] );
		remove_all_filters( 'job_manager_job_dashboard_nav_items' );
		remove_all_actions( 'job_manager_job_dashboard_view_calendar' );
		parent::tearDown();
	}

	/**
	 * Register a nav item and its view handler on the job dashboard.
	 *
	 * @param string $view       View name.
	 * @param string $label      Nav label.
	 * @param mixed  $capability Optional capability to require.
	 */
	private function add_nav_item( $view, $label, $capability = null ) {
		add_action( 'job_manager_job_dashboard_view_' . $view, '__return_null' );

		add_filter(
			'job_manager_job_dashboard_nav_items',
			function ( $nav_items ) use ( $view, $label, $capability ) {
				$item = [
					'label' => $label,
					'view'  => $view,
				];

				if ( null !== $capability ) {
					$item['capability'] = $capability;
				}

				$nav_items[ $view ] = $item;

				return $nav_items;
			}
		);
	}

	public function test_default_nav_contains_only_the_listings_view() {
		$nav_items = Job_Dashboard_Shortcode::instance()->get_nav_items();

		$this->assertSame( [ 'listings' ], array_keys( $nav_items ) );
		$this->assertSame( 'listings', $nav_items['listings']['view'] );
		$this->assertNotEmpty( $nav_items['listings']['label'] );
	}

	public function test_default_view_is_listings() {
		$this->assertSame( 'listings', Job_Dashboard_Shortcode::instance()->get_default_view() );
	}

	public function test_filter_can_register_a_custom_view() {
		$this->add_nav_item( 'calendar', 'Calendar' );

		$nav_items = Job_Dashboard_Shortcode::instance()->get_nav_items();

		$this->assertSame( [ 'listings', 'calendar' ], array_keys( $nav_items ) );
		$this->assertSame( 'Calendar', $nav_items['calendar']['label'] );
	}

	public function test_item_without_a_label_is_dropped() {
		$this->add_nav_item( 'calendar', '' );

		$nav_items = Job_Dashboard_Shortcode::instance()->get_nav_items();

		$this->assertArrayNotHasKey( 'calendar', $nav_items );
	}

	public function test_item_view_falls_back_to_the_array_key() {
		add_action( 'job_manager_job_dashboard_view_calendar', '__return_null' );
		add_filter(
			'job_manager_job_dashboard_nav_items',
			function ( $nav_items ) {
				$nav_items['calendar'] = [ 'label' => 'Calendar' ];

				return $nav_items;
			}
		);

		$nav_items = Job_Dashboard_Shortcode::instance()->get_nav_items();

		$this->assertArrayHasKey( 'calendar', $nav_items );
		$this->assertSame( 'calendar', $nav_items['calendar']['view'] );
	}

	public function test_item_requiring_a_missing_capability_is_dropped() {
		$this->add_nav_item( 'calendar', 'Calendar', 'manage_options' );

		$nav_items = Job_Dashboard_Shortcode::instance()->get_nav_items();

		$this->assertArrayNotHasKey( 'calendar', $nav_items );
	}

	public function test_item_requiring_a_capability_the_user_has_is_kept() {
		$this->login_as_admin();

		$this->add_nav_item( 'calendar', 'Calendar', 'manage_options' );

		$nav_items = Job_Dashboard_Shortcode::instance()->get_nav_items();

		$this->assertArrayHasKey( 'calendar', $nav_items );
	}

	public function test_item_without_a_view_handler_is_dropped() {
		add_filter(
			'job_manager_job_dashboard_nav_items',
			function ( $nav_items ) {
				$nav_items['calendar'] = [
					'label' => 'Calendar',
					'view'  => 'calendar',
				];

				return $nav_items;
			}
		);

		$nav_items = Job_Dashboard_Shortcode::instance()->get_nav_items();

		$this->assertArrayNotHasKey( 'calendar', $nav_items );
	}

	public function test_current_view_defaults_to_listings() {
		$shortcode = Job_Dashboard_Shortcode::instance();

		$this->assertSame( 'listings', $shortcode->get_current_view( $shortcode->get_nav_items() ) );
	}

	public function test_current_view_reads_a_registered_view() {
		$this->add_nav_item( 'calendar', 'Calendar' );
		$_GET['view'] = 'calendar';

		$shortcode = Job_Dashboard_Shortcode::instance();

		$this->assertSame( 'calendar', $shortcode->get_current_view( $shortcode->get_nav_items() ) );
	}

	public function test_unknown_view_falls_back_to_the_default() {
		$_GET['view'] = 'does-not-exist';

		$shortcode = Job_Dashboard_Shortcode::instance();

		$this->assertSame( 'listings', $shortcode->get_current_view( $shortcode->get_nav_items() ) );
	}

	public function test_view_requiring_a_missing_capability_falls_back_to_the_default() {
		$this->add_nav_item( 'calendar', 'Calendar', 'manage_options' );
		$_GET['view'] = 'calendar';

		$shortcode = Job_Dashboard_Shortcode::instance();

		$this->assertSame( 'listings', $shortcode->get_current_view( $shortcode->get_nav_items() ) );
	}

	public function test_array_view_input_falls_back_to_the_default() {
		$_GET['view'] = [ 'calendar' ];

		$shortcode = Job_Dashboard_Shortcode::instance();

		$this->assertSame( 'listings', $shortcode->get_current_view( $shortcode->get_nav_items() ) );
	}

	public function test_view_url_has_no_query_var_for_the_default_view() {
		$shortcode = Job_Dashboard_Shortcode::instance();

		$this->assertStringNotContainsString( 'view=', $shortcode->get_view_url( 'listings' ) );
	}

	public function test_view_url_includes_the_query_var_for_a_custom_view() {
		$shortcode = Job_Dashboard_Shortcode::instance();

		$this->assertStringContainsString( 'view=calendar', $shortcode->get_view_url( 'calendar' ) );
	}

	public function test_nav_items_include_a_url_for_each_item() {
		$this->add_nav_item( 'calendar', 'Calendar' );

		$nav_items = Job_Dashboard_Shortcode::instance()->get_nav_items();

		$this->assertStringContainsString( 'view=calendar', $nav_items['calendar']['url'] );
		$this->assertStringNotContainsString( 'view=', $nav_items['listings']['url'] );
	}
}
