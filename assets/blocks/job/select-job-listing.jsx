/**
 * External dependencies
 */
import { map } from 'lodash';

/**
 * WordPress dependencies
 */
import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const SelectJobListing = ( { attributes, jobListings, setAttributes } ) => {
	const options = map( jobListings, ( listing ) => ( {
		value: listing.id,
		label: listing.title.rendered,
	} ) );

	return (
		<SelectControl
			label={ __( 'Jobs listings' ) }
			value={ attributes.jobId }
			options={ options }
			onChange={ ( jobId ) => setAttributes( { jobId: parseInt( jobId ) } ) } />
	);
};

export default SelectJobListing;
