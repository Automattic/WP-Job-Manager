import chalk from 'chalk';
import { execSync } from "node:child_process";
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

( function main() {

	deleteOldZips();

	if ( merged ) {
		return;
	}

	const zip = uploadZip();
	addComment( zip );

} )();

/**
 * Remove previously uploaded plugin zip files for the PR.
 *
 */
function deleteOldZips() {

	const oldZips = JSON.parse( execSync( `curl -s -u "${ login }" "${ MEDIA_LIBRARY_ENDPOINT }?mime_type=application/zip&_fields=id,date,title,source_url"` ).toString() );
	if ( oldZips?.code || ! Array.isArray( oldZips ) ) {
		console.log( `[${ oldZips.code }]`, oldZips.message );
		return;
	}
	oldZips?.forEach( ( zip ) => {
		const
			title = zip.title.rendered;
		if ( title.match( new RegExp( `wp-job-manager-zip-${pr}` ) ) ) {
			console.log( `Deleting old plugin build ${ title }.zip` );
			execSync( `curl -s -u "${ login }" -X DELETE "${ MEDIA_LIBRARY_ENDPOINT }/${ zip.id }?force=true"` );
		}
	} )
}

/**
 * Upload plugin zip to media library.
 *
 * @returns {string} URL to plugin zip file.
 */
function uploadZip() {

	const id = `${ pr }-${ commit }`;

	const zip = execSync( `curl -u "${ login }" --http1.1 --data-binary @wp-job-manager.zip -H "Content-Disposition: attachment; filename=\"wp-job-manager-zip-${ id }.zip\"" ${ MEDIA_LIBRARY_ENDPOINT }?title=wp-job-manager-zip-${ id } | jq .source_url` ).toString().replaceAll( '"', '' ).trim();
	console.log( chalk.green( '✓' ), `Plugin file uploaded to ${ zip }` )

	return zip;
}

/**
 * Post a comment on the PR with a link to the plugin zip and playground.
 * @param {string} zip URL to plugin zip file.
 */
function addComment( zip ) {

	const [ , path, id ] = zip.match( 'wp-content/uploads/(.*)/wp-job-manager-zip-(.*).zip' );
	const playgroundLink = `https://wpjobmanager.com/playground/?core=${ path }/${ id }`

	const body = `Plugin built with the proposed changes (${ commit }):
📦 [Download plugin zip](${ zip })
▶️ [Open in playground](${ playgroundLink })`;

	const hasComment = JSON.parse( execSync( `gh pr view ${ pr } --comments --json comments --jq '.comments | map(.author.login)'` ).toString() ).includes( 'github-actions' );

	execSync( `gh pr comment ${ pr } ${ hasComment ? '--edit-last' : '' } --body "${ body.replaceAll( '"', '\\"' ) }"` )
	console.log( chalk.green( '✓' ), 'Comment added to PR.' );
}
