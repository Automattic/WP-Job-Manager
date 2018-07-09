/**
 * Returns all job listings
 *
 * @param  {Object} state
 * @return {Array}  All job listings
 */
export const getAllJobListings = ( state ) => state.jobListings.items || [];

/**
 * Returns all job types
 *
 * @param  {Object} state
 * @return {Array}  All job types
 */
export const getAllJobTypes = ( state ) => state.jobTypes.items || [];
