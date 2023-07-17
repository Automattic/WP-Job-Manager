/**
 * WordPress dependencies
 */
import { subscribe, select } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';

/**
 * Helper function to fire callbacks on editor lifecycles.
 *
 * @param {Object}   options
 * @param {Function} options.subscribeListener Callback called everytime the subscribe listener is called.
 * @param {Function} options.onSetDirty        Callback called when the editor becomes dirty.
 * @param {Function} options.onSaveStart       Callback called when editor starts saving.
 * @param {Function} options.onSave            Callback called when a save is completed.
 *
 * @return {Function} Unsubscribe function.
 */
const editorLifecycle = ( {
	subscribeListener = () => {},
	onSetDirty = () => {},
	onSaveStart = () => {},
	onSave = () => {},
} ) => {
	const coreEditorSelector = select( editorStore );
	let wasSaving = false;
	let wasDirty = false;
	let isNew    = false;

	const unsubscribe = subscribe( () => {
		subscribeListener();

		// Once identified as new, it will be considered new until the save.
		isNew = coreEditorSelector.isEditedPostNew() || isNew;

		const isDirty = coreEditorSelector.isEditedPostDirty();

		const isSaving =
			coreEditorSelector.isSavingPost() &&
			! coreEditorSelector.isAutosavingPost();

		if ( ! wasDirty && isDirty ) {
			// If editor becomes dirty.
			wasDirty = true;
			onSetDirty();
		} else {
			wasDirty = isDirty;
		}

		if ( wasSaving && ! isSaving ) {
			// If it completed a saving.
			wasSaving = isSaving;
			onSave( isNew );
			isNew = false;
		} else if ( ! wasSaving && isSaving ) {
			// If it started saving.
			wasSaving = isSaving;
			onSaveStart( isNew );
		} else {
			wasSaving = isSaving;
		}
	} );

	return unsubscribe;
};

export default editorLifecycle;
