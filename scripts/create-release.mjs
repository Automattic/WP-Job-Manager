#!/usr/bin/env zx

import 'zx/globals'

/**
 * External dependencies
 */
import fs from 'node:fs';
import process from 'node:process';
import { execSync } from 'node:child_process';

const PLUGINS = {
	'wp-job-manager': {
		file: 'wp-job-manager.php',
		constant: 'JOB_MANAGER_VERSION',
		repo: 'Automattic/wp-job-manager',
	},
};

const REMOTE = `origin`;

/* eslint-disable no-console */

// Get plugin information.
const pluginSlug         = process.argv[ 2 ];
const plugin             = PLUGINS[ pluginSlug ];
const pluginFileName     = plugin.file;
const pluginFileContents = fs.readFileSync( pluginFileName, 'utf8' );
const pluginVersion      = pluginFileContents.match( /Version: (.*)/ )[ 1 ];
const pluginName         = pluginFileContents.match( /Plugin Name: (.*)/ )[ 1 ];

const prNumber = process.argv[ 3 ];

const releaseNotes = getReleaseNotes();
updateChangelog();
commitChangelog();
tagRelease();
buildPluginZip();
await createGithubRelease();
setWorkflowStepOutput();
await success();

function getReleaseNotes() {

	// Normalize CRLF to LF: GitHub stores PR bodies edited in the web UI with
	// CRLF line endings, which would break the `\n`-based fence regex below.
	const prDescription = JSON.parse( execSync( `gh pr view ${ prNumber } -R ${ plugin.repo } --json body` ).toString() ).body.replace( /\r\n/g, '\n' );
	const releaseNotes  = prDescription
		.match( /### Release Notes\s*\n---([\S\s]*?)---/ )[ 1 ]
		.replace( /^- /gm, '* ' )
		.trim();

	return releaseNotes;
}

function updateChangelog() {

	// readme.txt's `== Changelog ==` is the single source of truth. It is the last
	// section in the file; entries are `### <version> - <date>` blocks, newest first,
	// trimmed to the most recent few. Older history lives in git and GitHub releases.
	const newEntry = `### ${ pluginVersion } - ${ new Date().toISOString().slice( 0, 10 ) }\n${ releaseNotes }`;

	let readme = fs.readFileSync( 'readme.txt', 'utf8' );

	const section = readme.match( /(== Changelog ==\n+)([\s\S]*)$/ );
	if ( ! section ) {
		throw new Error( 'Could not find the == Changelog == section in readme.txt.' );
	}

	// Split the existing section body into individual `### version` entries.
	const body     = section[ 2 ].trim();
	const existing = body ? body.split( /\n(?=### )/ ).map( ( entry ) => entry.trim() ) : [];

	// Prepend the new release and keep only the most recent 5 entries.
	const entries = [ newEntry.trim(), ...existing ].slice( 0, 5 );

	readme = readme.replace( /(== Changelog ==\n)[\s\S]*$/, `$1\n${ entries.join( '\n\n' ) }\n` );

	console.log( chalk.bold( 'Adding new release to changelog: ' ) );
	console.log( entries[ 0 ] );

	fs.writeFileSync( 'readme.txt', readme );
	console.log( chalk.green( '✓' ), 'readme.txt' );

}

function commitChangelog() {
	execSync( 'git add readme.txt' );
	execSync( `git commit -m "Update changelog for ${ pluginVersion }"` );
	execSync( `git push ${ REMOTE } HEAD` );
}

function tagRelease() {
	execSync( `git tag -a ${ pluginVersion } -m "Release ${ pluginVersion }"` );
	execSync( `git push ${ REMOTE } ${ pluginVersion }` );
}

function buildPluginZip() {
	execSync( `npm run build 1> /dev/null` );
}

function setWorkflowStepOutput() {
	execSync( `echo "version=${ pluginVersion }" >> "$GITHUB_OUTPUT"` );
}

async function createGithubRelease() {
	const pluginZip = `build/${ pluginSlug }.zip`
	await $`gh release create ${ pluginVersion } -R ${ plugin.repo } --title ${ `Version ${ pluginVersion }` } --notes ${ releaseNotes } ${ pluginZip }`
}

async function success() {
	console.log( chalk.bold.green( `✓ ${ pluginName } ${ pluginVersion } release created!` ) );
	const comment = `✅ **[${ pluginName } ${ pluginVersion } release](https://github.com/${ plugin.repo }/releases/tag/${ pluginVersion })** created!`;
	await $`gh pr comment ${ prNumber } -R ${ plugin.repo } --edit-last --body ${ comment }`

}
