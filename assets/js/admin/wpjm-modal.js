/**
 * Job Manager admin modal.
 *
 * @param {string}   selector
 * @param {string}   dialogSelector
 * @param {Function} openCallback
 */
const wpjmModal = ( selector, dialogSelector, openCallback ) => {
	const elements = document.querySelectorAll( selector );
	const dialog = document.querySelector( dialogSelector );

	elements.forEach( ( element ) => {
		element.addEventListener( 'click', function( event ) {
			event.preventDefault();
			dialog.showModal();
			openCallback( element, dialog );
		} );
	} );
}

export default wpjmModal;
