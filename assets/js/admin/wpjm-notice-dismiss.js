/**
 * WordPress dependencies
 */
import domReady from '@wordpress/dom-ready';

domReady( () => {

	/**
	 * Handle dismissing the notice by sending a request to the server.
	 *
	 * @param  element The DOM element of the container of the notice being dismissed.
	 */
	const handleDismiss = async( element ) => {
		const formData = new FormData();
		if ( element.dataset.dismissNotice ) {
			formData.append( 'notice', element.dataset.dismissNotice );
		}
		formData.append( 'action', element.dataset.dismissAction );
		formData.append( 'nonce', element.dataset.dismissNonce );

		fetch( ajaxurl, {
			method: 'POST',
			body: formData,
		} );

		await element.animate( [ { opacity: 0 } ], 100 ).finished;
		await element.animate(
			[ { opacity: 0 }, {
				opacity: 0,
				height: 0,
				paddingTop: 0,
				paddingBottom: 0,
			} ], 100,
		).finished;
		element.remove();

	};

	const wpjmNotices = document.querySelectorAll( '.wpjm-admin-notice, .wpjm-admin-modal-notice' );
	for ( const wpjmNotice of wpjmNotices ) {
		wpjmNotice.addEventListener( 'click', ( event ) => {

			if (
				wpjmNotice.dataset.dismissNonce &&
				wpjmNotice.dataset.dismissAction &&
				event.target.classList.contains( 'wpjm-notice-dismiss' )
			) {
				handleDismiss( wpjmNotice );
			}
			return true;
		} );
	}
} );
