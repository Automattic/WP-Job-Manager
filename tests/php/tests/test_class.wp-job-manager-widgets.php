<?php
/**
 * Tests for the plugin's widgets.
 *
 * @package wp-job-manager
 */

/**
 * @group widgets
 */
class WP_Test_WP_Job_Manager_Widgets extends WPJM_BaseTest {

	/**
	 * The plugin's widgets should opt into the block editor's REST pipeline so
	 * they can be edited and previewed through the Legacy Widget block (e.g. in
	 * the Site Editor on block themes).
	 *
	 * Regression test for #2851.
	 */
	public function test_widgets_are_shown_in_rest() {
		$widgets = [
			new WP_Job_Manager_Widget_Recent_Jobs(),
			new WP_Job_Manager_Widget_Featured_Jobs(),
		];

		foreach ( $widgets as $widget ) {
			$this->assertArrayHasKey(
				'show_instance_in_rest',
				$widget->widget_options,
				get_class( $widget ) . ' must declare the show_instance_in_rest widget option.'
			);
			$this->assertTrue(
				$widget->widget_options['show_instance_in_rest'],
				get_class( $widget ) . ' must set show_instance_in_rest to true.'
			);
		}
	}

	/**
	 * Rendering the Recent Jobs widget with a minimal instance should render the
	 * matching listing (and not fatal on the missing remote_position key, which
	 * is the #2851 regression the block-editor REST render path exposed).
	 *
	 * Regression test for #2851 (the block editor invokes widget() via REST render).
	 */
	public function test_recent_jobs_widget_renders_matching_listing() {
		$this->factory->job_listing->create( [ 'post_title' => 'Senior Widget Engineer' ] );

		$widget   = new WP_Job_Manager_Widget_Recent_Jobs();
		$instance = [ 'title' => 'Recent Jobs', 'number' => 5 ];

		ob_start();
		$widget->widget( $this->get_widget_args(), $instance );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'job_listings', $output );
		$this->assertStringContainsString( 'Senior Widget Engineer', $output );
	}

	/**
	 * Recent Jobs should also render when remote positions are enabled, so the
	 * instance carries a remote_position value and takes the in_array() branch.
	 *
	 * Regression test for #2851.
	 */
	public function test_recent_jobs_widget_renders_with_remote_position() {
		update_option( 'job_manager_enable_remote_position', 1 );
		$this->factory->job_listing->create(
			[
				'post_title' => 'Remote Widget Engineer',
				'meta_input' => [ '_remote_position' => 1 ],
			]
		);

		$widget   = new WP_Job_Manager_Widget_Recent_Jobs();
		$instance = [ 'title' => 'Recent Jobs', 'number' => 5, 'remote_position' => 'true' ];

		ob_start();
		$widget->widget( $this->get_widget_args(), $instance );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'job_listings', $output );
	}

	/**
	 * Rendering the Featured Jobs widget should render a matching featured
	 * listing (exercising the render loop, not just the no-jobs-found branch).
	 *
	 * Regression test for #2851.
	 */
	public function test_featured_jobs_widget_renders_matching_listing() {
		$this->factory->job_listing->create(
			[
				'post_title' => 'Featured Widget Engineer',
				'meta_input' => [ '_featured' => 1 ],
			]
		);

		$widget   = new WP_Job_Manager_Widget_Featured_Jobs();
		$instance = [ 'title' => 'Featured Jobs', 'number' => 5 ];

		ob_start();
		$widget->widget( $this->get_widget_args(), $instance );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'job_listings', $output );
		$this->assertStringContainsString( 'Featured Widget Engineer', $output );
	}

	/**
	 * Minimal sidebar args as passed to WP_Widget::widget().
	 *
	 * @return array
	 */
	private function get_widget_args() {
		return [
			'before_widget' => '',
			'after_widget'  => '',
			'before_title'  => '',
			'after_title'   => '',
			'widget_id'     => 'test-widget-1',
		];
	}
}
