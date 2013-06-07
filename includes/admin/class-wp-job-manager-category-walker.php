<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * WP_Job_Manager_Category_Walker class.
 *
 * @extends Walker
 */
class WP_Job_Manager_Category_Walker extends Walker {

	var $tree_type = 'category';
	var $db_fields = array ('parent' => 'parent', 'id' => 'term_id', 'slug' => 'slug' );

	/**
	 * @see Walker::start_el()
	 * @since 2.1.0
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param object $category Category data object.
	 * @param int $depth Depth of category in reference to parents.
	 * @param array $args
	 */
	function start_el( $output, $cat, $depth, $args ) {

		if ( ! empty( $args['hierarchical'] ) )
			$pad = str_repeat('&nbsp;', $depth * 3);
		else
			$pad = '';

		$cat_name = apply_filters( 'list_product_cats', $cat->name, $cat );

		$value = isset( $args['value'] ) && $args['value'] == 'id' ? $cat->term_id : $cat->slug;

		$output .= "\t<option class=\"level-$depth\" value=\"" . $value . "\"";

		if ( $value == $args['selected'] || ( is_array( $args['selected'] ) && in_array( $value, $args['selected'] ) ) )
			$output .= ' selected="selected"';

		$output .= '>';

		$output .= $pad . __( $cat_name, 'download_monitor' );

		if ( ! empty( $args['show_count'] ) )
			$output .= '&nbsp;(' . $cat->count . ')';

		$output .= "</option>\n";
	}
}