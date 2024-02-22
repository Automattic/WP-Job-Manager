import { debounce, filterZeroes, findIdInClassNames } from "./utils";
import { waitForSelector } from "./observers";

function createStatsQueue() {
	const alreadySent = {};
	let queue = [];

	const logThem = debounce( function ( statName, listingIds ) {
		const stats = listingIds.map( function ( id ) {
			alreadySent[id] = true;
			return { name: statName, post_id: id };
		});

		return window.wpjmLogStats( stats ).finally( function () {
			queue = [];
		});
	}, 1000 );

	return {
		queueListingImpressionStats: function ( statName, listingIds ) {
			listingIds.forEach( function (listingId ) {
				if ( ! alreadySent[listingId] ) {
					queue.push(listingId);
				}
			} );
			logThem( statName, queue );

		}
	};
}

function observeForVisibility( jobListingContainer, jobListingElement, visibleCallback, alreadyViewedListings ) {
	const options = {
		root: null,
		rootMargin: "0px",
		threshold: 1.0,
	};

	const observer = new IntersectionObserver(function ( entries ) {
		entries.forEach(function ( entry ) {
			if ( entry.isIntersecting && entry.intersectionRatio > 0.99 ) {
				const node = entry.target;
				if ( 1 === node.nodeType && node.classList.contains( 'job_listing' ) ) {
					const nodeId = findIdInClassNames( node );
					if ( nodeId > 0 && ! alreadyViewedListings[nodeId] ) {
						alreadyViewedListings[nodeId] = true;
						visibleCallback( node );
					}
				}
				observer.unobserve( node );
			}
		} );
	}, options );

	observer.observe( jobListingElement );
}

function waitForNextVisibleListing( listingVisibleCallback ) {
	const jobListingsContainer = document.querySelector( 'ul.job_listings' );
	const config = { childList: true };
	const alreadyViewed = {};

	const observer = new MutationObserver(function ( mutations ) {
		mutations.forEach(function ( mutation ) {
			mutation.addedNodes.forEach( function ( node ) {
				if ( 1 === node.nodeType && node.classList.contains( 'job_listing' ) ) {
					observeForVisibility( jobListingsContainer, node, listingVisibleCallback, alreadyViewed );
				}
			} );
		} );
	} );

	observer.observe( jobListingsContainer, config );
}

export function initListingImpression( stats ) {
	if ( 0 === stats.length ) {
		return;
	}
	const statName = stats[0].name;
	const debouncedSender = createStatsQueue();
	waitForSelector( 'li.job_listing' ).then( function () {
		const allVisibleListings = document.querySelectorAll('li.job_listing');
		const initialListingIds = filterZeroes( [...allVisibleListings].map( function ( elem ) {
			return findIdInClassNames( elem );
		} ) );
		debouncedSender.queueListingImpressionStats( statName, initialListingIds );

		waitForNextVisibleListing( function ( elem ) {
			const maybeId = findIdInClassNames( elem );
			maybeId > 0 && debouncedSender.queueListingImpressionStats( statName, [ maybeId ] );
		} );

	} );
}

