/**
 * WordPress dependencies
 */
import { select } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import domReady from '@wordpress/dom-ready';

/**
 * Internal dependencies
 */
import editorLifecycle from './editor-lifecycle';
import { postOpenPromoteModal } from './promote-job-modals';

domReady( () => {
	const coreEditorSelector = select( editorStore );
	const promoteDialog = document.querySelector( '#promote-dialog' );

	if ( ! promoteDialog ) {
		return;
	}

	editorLifecycle( {
		onSaveStart: () => {
			// Check if status is being changed to publish.
			if ( 'publish' === coreEditorSelector.getEditedPostAttribute( 'status' ) && 'publish' !== coreEditorSelector.getCurrentPostAttribute( 'status' ) ) {
				promoteDialog.showModal();
				postOpenPromoteModal( promoteDialog, window.wpjm.promoteUrl );
			}
		}
	} );
} );
