/**
 * WordPress dependencies
 */
import { combineReducers } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { UPDATE_JOB_LISTINGS, UPDATE_JOB_TYPES } from './action-types';

const jobListings = combineReducers( {
	items: ( state = [], action ) => action.type === UPDATE_JOB_LISTINGS ? action.listings : state,
} );

const jobTypes = combineReducers( {
	items: ( state = [], action ) => action.type === UPDATE_JOB_TYPES ? action.types : state,
} );

export default combineReducers( {
	jobListings,
	jobTypes
} );
