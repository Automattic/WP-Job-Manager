<?php
$editor = apply_filters( 'submit_job_form_wp_editor_args', array(
	'textarea_name' => isset( $field['name'] ) ? $field['name'] : $key,
	'media_buttons' => false,
	'textarea_rows' => 8,
	'quicktags'     => false,
	'tinymce'       => array(
		'plugins'                       => 'paste',
		'paste_as_text'                 => true,
		'paste_auto_cleanup_on_paste'   => true,
		'paste_remove_spans'            => true,
		'paste_remove_styles'           => true,
		'paste_remove_styles_if_webkit' => true,
		'paste_strip_class_attributes'  => true,
		'theme_advanced_buttons1'       => 'bold,italic,|,bullist,numlist,|,undo,redo,|,|,code',
		'theme_advanced_buttons2'       => '',
		'theme_advanced_buttons3'       => '',
		'theme_advanced_buttons4'       => ''
	),
) );
wp_editor( isset( $field['value'] ) ? esc_textarea( $field['value'] ) : '', $key, $editor );