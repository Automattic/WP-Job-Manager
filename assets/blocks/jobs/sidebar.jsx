
/**
 * External Dependencies.
 */
import _ from 'lodash';

/**
 * WordPress Dependencies.
 */
const { addFilter } = wp.hooks,
	{ __ } = wp.i18n,
	{ TextControl, SelectControl, CheckboxControl } = wp.components;


/**
 * Sidebar block attributes.
 */
const sidebarAttributesConfig = {
	perPage: {
		type: 'string',
		default: '',
	},
	orderBy: {
		type: 'string',
		default: 'featured',
	},
	order: {
		type: 'string',
		default: 'desc',
	},
	showPagination: {
		type: 'boolean',
		default: false,
	},
	showCategories: {
		type: 'boolean',
		default: true,
	},
	featured: {
		type: 'string',
		default: '',
	},
	filled: {
		type: 'string',
		default: '',
	},
};
addFilter(
	'wpjm_block_jobs_attributes_config',
	'addSidebarAttributes',
	( attributesConfig ) => (
		Object.assign( attributesConfig, sidebarAttributesConfig )
	)
);


/**
 * Transform the sidebar attributes into shortcode parameters.
 *
 * @param {Object} shortcodeParams The incoming shortcode parameters.
 * @param {Object} attributes      The block attributes.
 *
 * @return {Object} The new shortcode parameters.
 */
function getShortcodeParameters( shortcodeParams, attributes ) {
	const shortcodeParamNames = [
			'per_page',
			'order_by',
			'order',
			'show_pagination',
			'show_categories',
			'featured',
			'filled',
		];

	shortcodeParamNames.forEach( ( paramName ) => {
		const value = attributes[ _.camelCase( paramName ) ];

		if ( null !== value && '' !== value ) {
			shortcodeParams[ paramName ] = value;
		}
	} )

	return shortcodeParams;
}
addFilter( 'wpjm_block_jobs_shortcode_params', 'getShortcodeParameters', getShortcodeParameters );


/**
 * Sidebar component.
 */
export default function Sidebar( { attributes, setAttributes } ) {
	return (
		<div>
			<hr />
			<TextControl
				type="number"
				label={ __( 'Listings per page' ) }
				help={ __( 'Defaults to the value in Settings' ) }
				value={ attributes.perPage }
				onChange={ ( perPage ) => setAttributes( { perPage } ) }
			/>
			<SelectControl
				label={ __( 'Order by' ) }
				value={ attributes.orderBy }
				options={ [
					{ label: 'Featured', value: 'featured' },
					{ label: 'Title', value: 'title' },
					{ label: 'ID', value: 'ID' },
					{ label: 'Name', value: 'name' },
					{ label: 'Date Listed', value: 'date' },
					{ label: 'Date Modified', value: 'modified' },
					{ label: 'Random', value: 'rand' },
				] }
				onChange={ ( orderBy ) => setAttributes( { orderBy } ) }
			/>
			<SelectControl
				label={ __( 'Order' ) }
				value={ attributes.order }
				options={ [
					{ label: 'Descending', value: 'desc' },
					{ label: 'Ascending', value: 'asc' },
				] }
				onChange={ ( order ) => setAttributes( { order } ) }
			/>
			<CheckboxControl
				heading={ __( 'Show pagination' ) }
				label={ __( 'Should pagination be displayed?' ) }
				help={ __( 'If false, then a link to load more will be displayed instead' ) }
				checked={ attributes.showPagination }
				onChange={ ( showPagination ) => setAttributes( { showPagination } ) }
			/>
			<CheckboxControl
				heading={ __( 'Show categories' ) }
				label={ __( 'Should categories dropdown be displayed?' ) }
				checked={ attributes.showCategories }
				onChange={ ( showCategories ) => setAttributes( { showCategories } ) }
			/>
			<SelectControl
				label={ __( 'Featured listings' ) }
				value={ attributes.featured }
				options={ [
					{ label: 'Show All', value: '' },
					{ label: 'Show Featured', value: 'true' },
					{ label: 'Hide Featured', value: 'false' },
				] }
				onChange={ ( featured ) => setAttributes( { featured } ) }
			/>
			<SelectControl
				label={ __( 'Filled listings' ) }
				value={ attributes.filled }
				options={ [
					{ label: 'Default', value: '' },
					{ label: 'Show Filled', value: 'true' },
					{ label: 'Hide Filled', value: 'false' },
				] }
				onChange={ ( filled ) => setAttributes( { filled } ) }
			/>
		</div>
	)
}
