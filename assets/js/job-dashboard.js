/* global job_manager_job_dashboard */

import domReady from '@wordpress/dom-ready';

// eslint-disable-next-line camelcase
const { i18nConfirmDelete, overlayEndpoint, statsEnabled } = job_manager_job_dashboard;

function setupEvents( root ) {
	root
		.querySelectorAll( '.job-dashboard-action-delete' )
		.forEach( el => el.addEventListener( 'click', confirmDelete ) );
}

function setupActionsMenu() {
	document.addEventListener( 'pointerdown', event => {
		document.querySelectorAll( 'details.jm-ui-actions-menu[open]' ).forEach( menu => {
			if ( ! menu.contains( event.target ) ) {
				menu.open = false;
			}
		} );
	} );

	document.addEventListener( 'keydown', event => {
		if ( event.key !== 'Escape' ) {
			return;
		}
		document.querySelectorAll( 'details.jm-ui-actions-menu[open]' ).forEach( menu => {
			menu.open = false;
			menu.querySelector( 'summary' )?.focus();
		} );
	} );

	window.addEventListener( 'pageshow', event => {
		if ( ! event.persisted ) {
			return;
		}
		document.querySelectorAll( 'details.jm-ui-actions-menu[open]' ).forEach( menu => {
			menu.open = false;
		} );
	} );
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

	const dashboardRowId = eventOrId.target?.dataset.jobId;
	const adminRowId = eventOrId.target?.closest( 'tr' )?.id?.replace( 'post-', '' );

	const id = dashboardRowId ?? adminRowId ?? eventOrId;

	if ( ! id ) {
		return;
	}

	location.hash = id;

	const contentElement = overlayDialog.querySelector( '.jm-dialog-modal-content' );
	contentElement.innerHTML = '<a class="jm-ui-spinner"></a>';

	try {
		const response = await fetch( `${ overlayEndpoint }&job_id=${ id }` );

		if ( ! response.ok ) {
			throw new Error( response.statusText );
		}

		const { data } = await response.json();

		contentElement.innerHTML = data;
	} catch ( error ) {
		contentElement.innerHTML = `<div class="jm-notice color-error has-text-align-center" role="status">${ error.message }</div>`;
	}

	const clearHash = () => {
		history.replaceState( null, '', window.location.pathname + window.location.search );
		overlayDialog.removeEventListener( 'close', clearHash );
	};

	overlayDialog.addEventListener( 'close', clearHash );

	setupEvents( contentElement );
}

let editSaved = false;

function showEditOverlay( event ) {
	const link = event.target.closest?.( 'a.job-dashboard-action-edit' );
	const overlayDialog = document.getElementById( 'jmDashboardOverlay' );

	// Let modified clicks open the edit page in a new tab as usual.
	if (
		! link ||
		! overlayDialog ||
		event.button !== 0 ||
		event.metaKey ||
		event.ctrlKey ||
		event.shiftKey ||
		event.altKey
	) {
		return;
	}

	const url = new URL( link.href );

	if ( url.origin !== window.location.origin ) {
		return;
	}

	event.preventDefault();
	link.closest( 'details.jm-ui-actions-menu' )?.removeAttribute( 'open' );

	url.searchParams.set( 'jm_overlay', '1' );

	const frame = document.createElement( 'iframe' );
	frame.className = 'jm-job-edit-frame';
	frame.title = link.textContent.trim();
	frame.src = url.toString();
	frame.addEventListener( 'load', () => setupEditFrame( frame, link.href ) );

	overlayDialog.querySelector( '.jm-dialog-modal-content' ).replaceChildren( frame );

	if ( ! overlayDialog.open ) {
		overlayDialog.showModal();
	}
}

function setupEditFrame( frame, editUrl ) {
	const frameDocument = frame.contentDocument;

	// The page could not be framed (for example blocked by X-Frame-Options), open it directly instead.
	if ( ! frameDocument?.body ) {
		window.location.assign( editUrl );
		return;
	}

	if ( frameDocument.body.classList.contains( 'jm-job-edit-saved' ) ) {
		editSaved = true;
	}

	// Links like "View" in the save message should leave the overlay.
	frameDocument.addEventListener( 'click', event => {
		const link = event.target.closest?.( 'a[href]' );

		if ( link && ! link.target && ! link.getAttribute( 'href' ).startsWith( '#' ) ) {
			link.target = '_top';
		}
	} );

	// Key events inside the frame do not reach the dialog, so close it on Escape here.
	frameDocument.addEventListener( 'keydown', event => {
		if ( event.key === 'Escape' && ! event.defaultPrevented ) {
			frame.closest( 'dialog' )?.close();
		}
	} );
}

function setupEditOverlay() {
	const overlayDialog = document.getElementById( 'jmDashboardOverlay' );

	if ( ! overlayDialog ) {
		return;
	}

	document.addEventListener( 'click', showEditOverlay );

	// Reload without the hash, so the dashboard shows the saved changes and the stats overlay does not reopen.
	overlayDialog.addEventListener( 'close', () => {
		if ( editSaved ) {
			window.location.assign( window.location.pathname + window.location.search );
		}
	} );
}

function setupStatsOverlay() {
	document
		.querySelectorAll( '.jm-dashboard-job .job-title, tr.job_listing td.column-stats' )
		.forEach( el => el.addEventListener( 'click', showOverlay ) );

	const urlHash = window.location.hash?.substring( 1 );

	if ( urlHash > 0 ) {
		showOverlay( +urlHash );
	}
}

domReady( () => {
	setupEvents( document );
	setupActionsMenu();
	setupEditOverlay();

	if ( statsEnabled ) {
		setupStatsOverlay();
	}
} );
