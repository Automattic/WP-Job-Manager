/* global job_manager_job_dashboard */

import domReady from '@wordpress/dom-ready';

// eslint-disable-next-line camelcase
const { i18nConfirmDelete, overlayEndpoint } = job_manager_job_dashboard;

function setupEvents( root ) {
	root
		.querySelectorAll( '.job-dashboard-action-delete' )
		.forEach( el => el.addEventListener( 'click', confirmDelete ) );
}

function confirmDelete( event ) {
	// eslint-disable-next-line no-alert
	if ( ! window.confirm( i18nConfirmDelete ) ) {
		event.preventDefault();
	}
}

async function showOverlay( event ) {
	const overlayDialog = document.getElementById( 'jmDashboardOverlay' );

	if ( ! overlayDialog ) {
		return true;
	}

	event.preventDefault();
	overlayDialog.showModal();

	const contentElement = overlayDialog.querySelector( '.jm-dialog-modal-content' );
	contentElement.innerHTML = '<a class="jm-ui-spinner"></a>';

	const { success, data } = await (
		await fetch( `${ overlayEndpoint }?job_id=${ this.dataset.jobId }` )
	 ).json();

	contentElement.innerHTML = data;

	setupEvents( contentElement );
}

domReady( () => {
	setupEvents( document );

	document
		.querySelectorAll( '.jm-dashboard-job .job-title' )
		.forEach( el => el.addEventListener( 'click', showOverlay ) );
} );
