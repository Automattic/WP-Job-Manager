
export function waitForSelector( selector ) {
	return new Promise(function ( resolve ) {
		if (document.querySelector(selector)) {
			return resolve(document.querySelector(selector));
		}

		const observer = new MutationObserver(function () {
			if (document.querySelector(selector)) {
				observer.disconnect();
				resolve(document.querySelector(selector));
			}
		});

		observer.observe(document.documentElement, {
			childList: true,
			subtree: true
		});
	});
}
