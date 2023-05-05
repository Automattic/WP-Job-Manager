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
	const handleDismiss = ( element ) => {
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
	};

	const wpjmNotices = document.querySelectorAll('.wpjm-admin-notice' );
	for (const wpjmNotice of wpjmNotices) {
		wpjmNotice.addEventListener('click', (event) => {
			const noticeContainer = event.target.closest('.wpjm-admin-notice');
			if (!noticeContainer) {
				return;
			}

			if (
				noticeContainer.dataset.dismissNonce &&
				noticeContainer.dataset.dismissAction &&
				event.target.classList.contains('notice-dismiss')
			) {
				handleDismiss(noticeContainer);
			}
		});
	}
} );
