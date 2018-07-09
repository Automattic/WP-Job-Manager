/**
 * WordPress dependencies
 */
import { apiRequest } from '@wordpress';
import { dispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { STORE_KEY } from './name';

export const getAllJobListings = async () => {
	const listings = await apiRequest( { path: '/wp/v2/job-listings?_embed' } );
	dispatch( STORE_KEY ).updateJobListings( listings );
};

export const getAllJobTypes = async () => {
	const types = await apiRequest( { path: '/wp/v2/job-types' } );
	dispatch( STORE_KEY ).updateJobTypes( types );
};
