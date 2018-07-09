/**
 * Internal dependencies
 */
import { UPDATE_JOB_LISTINGS, UPDATE_JOB_TYPES } from './action-types';

/**
 * Creates an updateJobListings action with a new set of job listings
 *
 * @param  {Array} listings
 * @return {Object} Action object
 */
export const updateJobListings = ( listings ) => ( { type: UPDATE_JOB_LISTINGS, listings } );

/**
 * Creates an updateJobTypes action with a new set of job types
 *
 * @param  {Array} types
 * @return {Object} Action object
 */
export const updateJobTypes = ( types ) => ( { type: UPDATE_JOB_TYPES, types } );
