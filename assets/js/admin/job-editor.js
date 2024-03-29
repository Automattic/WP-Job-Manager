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

// Open promote dialog when it's publishing a job.
domReady( () => {
	const coreEditorSelector = select( editorStore );
	const promoteDialog = document.querySelector( '#promote-dialog' );

	if ( ! promoteDialog ) {
		return;
	}

	let jobWasPublished = false;

	editorLifecycle( {
		onSaveStart: () => {
			// Mark if status is being changed to publish.
			jobWasPublished =
				'publish' === coreEditorSelector.getEditedPostAttribute( 'status' ) &&
				'publish' !== coreEditorSelector.getCurrentPostAttribute( 'status' );
		},
		onSave: () => {
			const meta = coreEditorSelector.getCurrentPostAttribute( 'meta' );

			// Open dialog when job was published and it's not promoted.
			if ( jobWasPublished && '1' !== meta?._promoted ) {
				promoteDialog.showModal();
				postOpenPromoteModal( promoteDialog, window.wpjm.promoteUrl );
			}
		},
	} );
} );
