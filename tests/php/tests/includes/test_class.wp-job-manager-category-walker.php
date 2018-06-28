<?php

class WP_Test_WP_Job_Manager_Category_Walker extends WPJM_BaseTest {
	private $terms;

	public function setUp() {
		parent::setUp();
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-category-walker.php';
	}

	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Category_Walker::start_el
	 */
	public function test_start_el() {
		$terms = $this->get_terms();
		$this->assertCount( 1, $terms );
		$walker = new WP_Job_Manager_Category_Walker();
		// Typical call.
		$test_output_a = '';
		$walker->start_el( $test_output_a, $terms[0], 11 );
		$this->assertContains( $terms[0]->name, $test_output_a );
		$this->assertContains( 'level-11', $test_output_a );

		// With empty county.
		$test_output_b = '';
		$walker->start_el(
			$test_output_b, $terms[0], 11, array(
				'show_count'   => true,
				'hierarchical' => true,
			)
		);
		$this->assertContains( '&nbsp;(0)', $test_output_b );
		$this->assertContains( str_repeat( '&nbsp;', 33 ), $test_output_b );

		// With selected.
		$test_output_c = '';
		$walker->start_el( $test_output_c, $terms[0], 0, array( 'selected' => $terms[0]->slug ) );
		$this->assertContains( 'selected="selected"', $test_output_c );
	}

	private function setup_terms() {
		return $this->terms = $this->factory->term->create_many( 1 );
	}

	private function get_terms() {
		$terms                = $this->setup_terms();
		$args                 = array();
		$args['taxonomy']     = WP_UnitTest_Factory_For_Term::DEFAULT_TAXONOMY;
		$args['pad_counts']   = 1;
		$args['hierarchical'] = 1;
		$args['hide_empty']   = 0;
		$args['show_count']   = 1;
		$args['selected']     = array_pop( $terms );
		$args['menu_order']   = false;
		return get_terms( $args );
	}
}
