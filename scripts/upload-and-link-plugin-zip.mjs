#!/usr/bin/env zx

import process from "node:process";
import { parseArgs } from "node:util";

const { env } = process;

const { values: { pr, commit, merged } } = parseArgs( {
	options: {
		pr: { type: "string" },
		commit: { type: "string" },
		merged: { type: "boolean", default: false },
	},
} );

const MEDIA_LIBRARY_ENDPOINT = "https://wpjobmanager.com/wp-json/wp/v2/media"
const login = env.WPJMCOM_API_LOGIN;

if ( ! login ) {
	console.log( 'WPJobManager.com secrets not available, exiting.' );
	process.exit( 0 );
}

await deleteOldZips();

if (!merged) {
	try {
		const zip = await uploadZip();
		await addLinksToPR(zip);
	} catch (error) {
		console.log( 'Failed to delete old plugin zips.' );
		console.log( error.message );
		console.log( error.stack );
		process.exit( 1 );
	}
}

/**
 * Remove previously uploaded plugin zip files for the PR.
 */
async function deleteOldZips() {
	const oldZips = JSON.parse( (await $`curl -s -u "${login}" "${MEDIA_LIBRARY_ENDPOINT}?mime_type=application/zip&_fields=id,date,title,source_url"`).stdout );
	if ( oldZips?.code || ! Array.isArray( oldZips ) ) {
		console.log( `[${ oldZips.code }]`, oldZips.message );
		return;
	}
	for ( const zip of oldZips ) {
		const title = zip.title.rendered;
		if ( title.startsWith( `wp-job-manager-zip-${ pr }-` ) ) {
			console.log( `Deleting old plugin build ${ title }.zip` );
			await $`curl -s -u "${login}" -X DELETE "${MEDIA_LIBRARY_ENDPOINT}/${zip.id}?force=true"`;
		}
	}
}

/**
 * Upload plugin zip to media library.
 *
 * @returns {string} URL to plugin zip file.
 */
async function uploadZip() {
	const id = `${ pr }-${ commit.substring( 0, 8 ) }`;
	const response = (await $`curl -u "${ login }" --http1.1 --data-binary @wp-job-manager.zip -H "Content-Disposition: attachment; filename=\"wp-job-manager-zip-${ id }.zip\"" ${ MEDIA_LIBRARY_ENDPOINT }?title=wp-job-manager-zip-${ id }`).stdout;
	const zip = JSON.parse( response )?.source_url?.replaceAll( '"', '' ).trim();
	if ( ! zip ) {
		throw new Error( response );
	}
	console.log( chalk.green( '✓' ), `Plugin file uploaded to ${ zip }` )

	return zip;
}

/**
 * Add a link to the plugin zip and the playground to the PR description.
 *
 * @param {string} zip URL to plugin zip file.
 */
async function addLinksToPR( zip ) {
	const [ , path, id ] = zip.match('wp-content/uploads/(.*)/wp-job-manager-zip-(.*).zip');
	const playgroundLink = `https://wpjobmanager.com/playground/?core=${ path }/${ id }`
	const links = `
<!-- wpjm:plugin-zip -->
----

| Plugin build for ${ commit } <a href="#"><img width=600></a> |
| ------------------------------------------------------------ |
| 📦 [Download plugin zip](${ zip })                       |
| ▶️ [Open in playground](${ playgroundLink })             |

<!-- /wpjm:plugin-zip -->
`;
	let body = (await $`gh pr view ${ pr } --json body --jq .body`).stdout;
	body = body.replace( /((<!-- wpjm:plugin-zip -->([\s\S]*)<!-- \/wpjm:plugin-zip -->)|$)/, links );

	echo( await $`gh pr edit ${ pr } --body "${ body }"` );
	console.log( chalk.green( '✓' ), 'Plugin build links added to PR.' );
}
