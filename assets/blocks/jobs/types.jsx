
/**
 * External Dependencies.
 */
import _ from 'lodash';

/**
 * WordPress Dependencies.
 */
const { Component } = wp.element,
	{ __ } = wp.i18n,
	{ addFilter } = wp.hooks,
	{
		withAPIData,
		CheckboxControl,
		ToggleControl,
	} = wp.components;


/**
 * Job Types block attributes.
 */
const jobTypesAttributesConfig = {
	showJobTypeFilters: {
		type: 'boolean',
		default: true,
	},
	includedJobTypes: {
		type: 'string', // JSON object
		default: '{}',
	},
	allJobTypes: {
		type: 'string', // JSON array
		default: '[]',
	}
};
addFilter(
	'wpjm_block_jobs_attributes_config',
	'addJobTypesAttributes',
	( attributesConfig ) => (
		Object.assign( attributesConfig, jobTypesAttributesConfig )
	)
);


/**
 * Transform a job type attribute value from a JSON-encoded object to a
 * comma-separated string.
 *
 * @param {string} value The JSON-encoded job type attribute value.
 *
 * @return {?string} Comma-separated value string for the attribute, or null.
 */
function transformJobTypeValue( value ) {
	let obj;
	try {
		obj = JSON.parse( value );
	} catch ( error ) {
		// If we cannot parse the value, give up.
		return null;
	}

	// Get the keys whose values are truthy.
	const keysToInclude = _.pickBy( obj, ( val ) => val );

	// Create a comma-separated string.
	let strValue = Object.keys( keysToInclude ).join( ',' );

	if ( ! strValue ) {
		strValue = null;
	}

	return strValue;
}

/**
 * Transform the Job Types attributes into shortcode parameters.
 *
 * @param {Object} shortcodeParams The incoming shortcode parameters.
 * @param {Object} attributes      The block attributes.
 *
 * @return {Object} The new shortcode parameters.
 */
function getShortcodeParameters( shortcodeParams, attributes ) {
	const jobTypes = transformJobTypeValue( attributes.includedJobTypes );
	const newShortcodeParams = { ...shortcodeParams };

	if ( attributes.showJobTypeFilters ) {
		if ( jobTypes ) {
			newShortcodeParams.selected_job_types = jobTypes;
		}
	} else {
		if ( jobTypes ) {
			newShortcodeParams.job_types = jobTypes;
		} else {
			// To make sure the checkboxes are not shown, add all job types to
			// the list if jobTypes is empty
			newShortcodeParams.job_types = JSON.parse( attributes.allJobTypes ).join( ',' );
		}
	}

	return newShortcodeParams;
}
addFilter( 'wpjm_block_jobs_shortcode_params', 'getShortcodeParameters', getShortcodeParameters );


/**
 * UI for filtering job types.
 */
class Types extends Component {
	state = {
		/**
	 	 * Initialize object from JSON value in attributes. When this object
	 	 * gets updates, we will also update the JSON attribute.
	 	 */
		includedJobTypes: JSON.parse( this.props.attributes.includedJobTypes ),

		/**
		 * Keep track of whether we have the API data.
		 */
		haveAPIData: false,
	}

	/**
	 * Determine whether the API has returned the job types data from the
	 * server. If we have just received it, update the `allJobTypes` attribute.
	 *
	 * @return {boolean} `true` if we have the API data, `false` otherwise.
	 */
	haveAPIData() {
		if ( ! this.state.haveAPIData ) {
			const { types } = this.props;

			if ( types && ! types.isLoading && 'undefined' !== typeof types.data ) {
				// We have received the data
				this.props.setAttributes( {
					allJobTypes: JSON.stringify( types.data.map( ( type ) => type.slug ) ),
				} );
				this.setState( { haveAPIData: true } );
			}
		}

		return this.state.haveAPIData;
	}

	/**
	 * Check whether the given type should be included.
	 *
	 * @param {string} typeSlug The slug of the job type to check.
	 *
	 * @return {boolean} `true` if the given type should be included, `false`
	 *                          otherwise.
	 */
	isIncluded( typeSlug ) {
		const { includedJobTypes } = this.state;
		return _.isEmpty( includedJobTypes ) || includedJobTypes[ typeSlug ];
	}

	/**
	 * Handle adding or removing a Job Type from the includedJobTypes object.
	 * If all types are selected, the object is emptied.
	 *
	 * @param {string}  typeSlug The type slug to add or remove.
	 * @param {boolean} add      Whether to add the type (otherwise we will
	 *                           remove).
	 *
	 * @return {Object} A copy of the object with the given type added or removed.
	 */
	addOrRemoveType( typeSlug, add ) {
		// Do nothing if the API hasn't returned yet.
		if ( ! this.haveAPIData() ) {
			return;
		}

		// Make a copy of the object to modify
		let includedJobTypes = { ...this.state.includedJobTypes };

		// If selectedTypes is empty, fill it up.
		if ( _.isEmpty( includedJobTypes ) ) {
			this.props.types.data.forEach( ( type ) => {
				includedJobTypes[ type.slug ] = true;
			} );
		}

		// Check or uncheck the type.
		includedJobTypes[ typeSlug ] = add;

		// If all types are selected, empty the selectedTypes object.
		if ( Object.values( includedJobTypes ).every( ( val ) => val ) ) {
			includedJobTypes = {};
		}

		this.setState( { includedJobTypes } );
		this.props.setAttributes( {
			includedJobTypes: JSON.stringify( includedJobTypes ),
		} );
	}

	/**
	 * Render the component.
	 */
	render() {
		const { types, attributes, setAttributes, isSelected, className } = this.props;

		if ( ! this.haveAPIData() ) {
			return <p className={ className }>{ __( 'Loading Job Types...' ) }</p>
		}

		// Only render if the block is selected or we are displaying filters on
		// the frontend.
		return ( isSelected || attributes.showJobTypeFilters ) && (
			<div className={ className }>
				{ isSelected && attributes.showFilters && (
					<ToggleControl
						label={ __( 'Allow user to filter Job Types?' ) }
						checked={ attributes.showJobTypeFilters }
						onChange={
							( showJobTypeFilters ) => setAttributes( { showJobTypeFilters } )
						}
					/>
				) }
				<ul>
					{ types.data.map( ( type ) => (
						<li>
							<CheckboxControl
								label={ type.name }
								checked={ this.isIncluded( type.slug ) }
								onChange={
									( addType ) => this.addOrRemoveType( type.slug, addType )
								}
							/>
						</li>
					) ) }
				</ul>
			</div>
		);
	}
}

/**
 * Export the component using the `withAPIData` Higher Order Component.
 */
export default withAPIData( () => ( {
	types: '/wp/v2/job-types',
} ) )( Types );
