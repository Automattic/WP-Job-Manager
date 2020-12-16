<?php
/**
 * Shows the right `textarea` form field with WP Editor on job listing forms.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/form-fields/wp-editor-field.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     wp-job-manager
 * @category    Template
 * @version     1.31.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$editor = apply_filters(
	'submit_job_form_wp_editor_args',
	[
		'textarea_name' => isset( $field['name'] ) ? $field['name'] : $key,
		'media_buttons' => false,
		'textarea_rows' => 8,
		'quicktags'     => false,
		'editor_css'    => '<style> .mce-top-part button { background-color: rgba(0,0,0,0.0) !important; } </style>',
		'tinymce'       => [
			'plugins'                       => 'lists,paste,tabfocus,wplink,wordpress',
			'paste_as_text'                 => true,
			'paste_auto_cleanup_on_paste'   => true,
			'paste_remove_spans'            => true,
			'paste_remove_styles'           => true,
			'paste_remove_styles_if_webkit' => true,
			'paste_strip_class_attributes'  => true,
			'toolbar1'                      => 'bold,italic,|,bullist,numlist,|,link,unlink,|,undo,redo',
			'toolbar2'                      => '',
			'toolbar3'                      => '',
			'toolbar4'                      => '',
		],
	]
);
wp_editor( isset( $field['value'] ) ? wp_kses_post( $field['value'] ) : '', $key, $editor );
if ( ! empty( $field['description'] ) ) :
	?><small class="description"><?php echo wp_kses_post( $field['description'] ); ?></small><?php endif; ?>
