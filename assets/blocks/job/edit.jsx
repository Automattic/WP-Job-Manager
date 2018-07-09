/**
 * External dependencies
 */
import { find } from 'lodash';

/**
 * WordPress dependencies
 */
import { withSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import JobListing from './job-listing.jsx';
import SelectJobListing from './select-job-listing.jsx';

/**
 * Edit UI for the Job block.
 */
const EditJob = ( { attributes, isSelected, jobListings, jobTypes, setAttributes } ) => {
	const listing = find( jobListings, { id: attributes.jobId } );

	if ( ! isSelected && listing ) {
		return <JobListing listing={ listing } jobTypes={ jobTypes } />;
	}

	return <SelectJobListing attributes={ attributes } jobListings={ jobListings } setAttributes={ setAttributes } />;
};

export default withSelect( ( select, props ) => ( {
	jobListings: select( 'wp-job-manager' ).getAllJobListings(),
	jobTypes: select( 'wp-job-manager' ).getAllJobTypes()
} ) )( EditJob );
