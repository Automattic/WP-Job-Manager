/**
 * WordPress dependencies
 */
import { subscribe, select } from '@wordpress/data';

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
	const coreEditorSelector = select( 'core/editor' );
	let wasSaving = false;
	let wasDirty = false;

	const unsubscribe = subscribe( () => {
		subscribeListener();

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
			onSave();
		} else if ( ! wasSaving && isSaving ) {
			// If it started saving.
			wasSaving = isSaving;
			onSaveStart();
		} else {
			wasSaving = isSaving;
		}
	} );

	return unsubscribe;
};

export default editorLifecycle;
