
/**
 * WordPress Dependencies.
 */
const { registerBlockType } = wp.blocks,
	{ applyFilters } = wp.hooks;

/**
 * Internal Dependencies.
 */
import './style.scss';
import edit from './edit.jsx';


/**
 * Base attributes configuration for the Jobs block. Other attributes may be
 * added using the `wpjm_block_jobs_attributes_config` filter.
 */
const attributesConfig = {
	showFilters: {
		type: 'boolean',
		default: true,
	},
	keywords: {
		type: 'string',
		default: '',
	},
	location: {
		type: 'string',
		default: '',
	},
};


/**
 * Register the Jobs block.
 */
registerBlockType( 'wp-job-manager/jobs', {
	title: 'Jobs',
	icon: 'list-view',
	category: 'common',

	attributes: applyFilters( 'wpjm_block_jobs_attributes_config', attributesConfig ),

	edit,

	save: function( { attributes } ) {
		let shortcodeParams = {};

		if ( attributes.keywords ) {
			shortcodeParams.keywords = attributes.keywords;
		}
		if ( attributes.location ) {
			shortcodeParams.location = attributes.location;
		}
		shortcodeParams.show_filters = attributes.showFilters;

		shortcodeParams = applyFilters(
			'wpjm_block_jobs_shortcode_params',
			shortcodeParams,
			attributes
		);

		let paramsString = Object.entries( shortcodeParams ).map(
			( [ attr, value ] ) => `${attr}="${value}"`
		).join( ' ' );

		return `[jobs ${paramsString}]`;
	},

} );
