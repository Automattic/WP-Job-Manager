import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useEntityProp } from '@wordpress/core-data';

import meta from './job-title.block.json';

registerBlockType( 'wp-job-manager/job-title', {
	...meta,
	edit: function Edit( { context: { postType, postId } } ) {
		const title = useEntityProp( 'postType', postType, 'post_title', postId );
		return <div> { postId ? title : __( 'Job Title', 'wp-job-manager' ) }</div>;
	},
} );
