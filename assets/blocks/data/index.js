/**
 * WordPress dependencies
 */
import { registerStore } from '@wordpress/data';

/**
 * Internal dependencies
 */
import reducer from './reducer';
import * as actions from './actions';
import * as resolvers from './resolvers';
import * as selectors from './selectors';
import { STORE_KEY } from './name';

export default () => registerStore( STORE_KEY, {
	actions,
	reducer,
	resolvers,
	selectors,
} );
