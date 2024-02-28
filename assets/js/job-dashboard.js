/* global job_manager_job_dashboard */

import domReady from '@wordpress/dom-ready';

import './ui';

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

async function showOverlay( eventOrId ) {
	const overlayDialog = document.getElementById( 'jmDashboardOverlay' );

	if ( ! overlayDialog ) {
		return true;
	}

	eventOrId.preventDefault?.();
	overlayDialog.showModal();

	const id = eventOrId.target?.dataset.jobId ?? eventOrId;

	if ( ! id ) {
		return;
	}

	location.hash = id;

	const contentElement = overlayDialog.querySelector( '.jm-dialog-modal-content' );
	contentElement.innerHTML = '<a class="jm-ui-spinner"></a>';

	try {
		const response = await fetch( `${ overlayEndpoint }?job_id=${ id }` );

		if ( ! response.ok ) {
			throw new Error( response.statusText );
		}

		const { data } = await response.json();

		contentElement.innerHTML = data;
	} catch ( error ) {
		contentElement.innerHTML = `<div class="jm-notice color-error has-text-align-center" role="status">${ error.message }</div>`;
	}

	const clearHash = () => {
		history.replaceState( null, '', window.location.pathname );
		overlayDialog.removeEventListener( 'close', clearHash );
	};

	overlayDialog.addEventListener( 'close', clearHash );

	setupEvents( contentElement );
}

domReady( () => {
	setupEvents( document );

	document
		.querySelectorAll( '.jm-dashboard-job .job-title' )
		.forEach( el => el.addEventListener( 'click', showOverlay ) );

	const urlHash = window.location.hash?.substring( 1 );

	if ( urlHash > 0 ) {
		showOverlay( +urlHash );
	}
} );
