import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

import meta from './job-salary.block.json';

registerBlockType( meta.name, {
	...meta,
	edit: function Edit( { context: { postId } } ) {
		return postId ? (
			<ServerSideRender block="wp-job-manager/job-salary" attributes={ { postId } } />
		) : (
			__( '$50.000 / yr', 'wp-job-manager' )
		);
	},
} );
