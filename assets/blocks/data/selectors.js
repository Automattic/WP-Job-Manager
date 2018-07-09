/**
 * Returns all job listings
 *
 * @param  {Object} state
 * @return {Array}
 */
export const getAllJobListings = state => state.jobListings.items || [];

/**
 * Returns all job types
 *
 * @param  {Object} state
 * @return {Array}
 */
export const getAllJobTypes = state => state.jobTypes.items || [];
