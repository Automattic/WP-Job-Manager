<?php wp_dropdown_categories( array( 
	'taxonomy'        => 'job_listing_category', 
	'hierarchical'    => 1, 
	'show_option_all' => false,
	'name'            => isset( $field['name'] ) ? $field['name'] : $key, 
	'orderby'         => 'name', 
	'selected'        => isset( $field['value'] ) ? $field['value'] : $field['default'],
	'hide_empty'      => false
) );