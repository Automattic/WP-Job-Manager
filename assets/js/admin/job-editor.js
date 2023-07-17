/**
 * WordPress dependencies
 */
import { select } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';

/**
 * Internal dependencies
 */
import editorLifecycle from './editor-lifecycle';

const coreEditorSelector = select( editorStore );

editorLifecycle( {
	onSaveStart: () => {
		// Check if status is being changed to publish.
		if ( 'publish' === coreEditorSelector.getEditedPostAttribute( 'status' ) && 'publish' !== coreEditorSelector.getCurrentPostAttribute( 'status' ) ) {
			const jobId = coreEditorSelector.getCurrentPostId();

			console.log( jobId );
		}
	}
} );
