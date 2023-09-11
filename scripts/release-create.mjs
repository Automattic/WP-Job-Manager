/**
 * External dependencies
 */
// import { config } from 'dotenv';
import fs from 'fs';
import process from 'process';
import chalk from 'chalk';
import { execSync } from 'child_process';

const PLUGINS = {
	'wp-job-manager': {
		file: 'wp-job-manager.php',
		constant: 'JOB_MANAGER_VERSION',
		repo: 'yscik/wp-job-manager',
	},
};

const REMOTE = `testing`;

/* eslint-disable no-console */

// // Processes the .env variables.
// config();

// Get plugin information.
const pluginSlug         = process.argv[ 2 ];
const plugin             = PLUGINS[ pluginSlug ];
const pluginFileName     = plugin.file;
const pluginFileContents = fs.readFileSync( pluginFileName, 'utf8' );
const pluginVersion      = pluginFileContents.match( /Version: (.*)/ )[ 1 ];
const pluginName         = pluginFileContents.match( /Plugin Name: (.*)/ )[ 1 ];

const prNumber = process.argv[ 3 ];

const releaseNotes = getReleaseChangelog();

updateChangelog();
commitChangelog();
tagRelease();
buildPluginZip();
createGithubRelease();

console.log( chalk.bold.green( `✓ ${ pluginName } ${ pluginVersion } released!` ) );
console.log( `The GitHub release entry will trigger a deploy to WordPress.org. \nTrack here: https://github.com/${ plugin.repo }/actions/workflows/deploy-wporg-release.yml` );
execSync( ` open https://github.com/${ plugin.repo }/actions/workflows/deploy-wporg-release.yml` );

function getReleaseChangelog() {
	// Get PR description
	const prDescription    = execSync( `gh pr view ${ prNumber } -R ${ plugin.repo } --json body` ).toString();
	// Get changelog section
	const changelogSection = prDescription.match( /### (?:Changelog|Release Notes)([\S\s]*?)(?:###|<!--)/ )[ 1 ]
		.replace( /\\n/g, '\n' )
		.replace( /\\r/g, '\r' )
		.replace( /^- /gm, '* ' )
		.trim();

	return changelogSection;
}

function updateChangelog() {

	let changelog = fs.readFileSync( 'changelog.txt', 'utf8' );

	const release = `## ${ pluginVersion } - ${ new Date().toISOString().slice( 0, 10 ) }
${ releaseNotes }`;

	changelog = changelog.replace( /^(# .*\n)/, '$1\n' + release + "\n" );

	const releases = [ ...changelog.matchAll( /(^##[\S\s]*?(?=##))/mg ) ].map( ( match ) => match[ 1 ] );

	console.log( chalk.bold( 'Adding new release to changelog: ' ) );
	console.log( releases[ 0 ] )

	let readme = fs.readFileSync( 'readme.txt', 'utf8' );
	readme     = readme.replace( /(== Changelog ==\n)([\s\S]+)/gm, `$1\n${ releases.slice( 0, 5 ).join( '' ) }` );

	fs.writeFileSync( 'changelog.txt', changelog );
	console.log( chalk.green( '✓' ), 'changelog.txt' );

	fs.writeFileSync( 'readme.txt', readme );
	console.log( chalk.green( '✓' ), 'readme.txt' );

}

function commitChangelog() {
	execSync( 'git add changelog.txt readme.txt' );
	execSync( `git commit -m "Update changelog for ${ pluginVersion }"` );
	execSync( `git push ${ REMOTE } HEAD` );
}

function tagRelease() {
	execSync( `git tag -a ${ pluginVersion } -m "Release ${ pluginVersion }"` );
	execSync( `git push ${ REMOTE } ${ pluginVersion }` );
}

function buildPluginZip() {
	execSync( `npm run build` );
}

function createGithubRelease() {
	const notes = releaseNotes.replace( '"', '\\"' );
	execSync( `gh release create ${ pluginVersion } -R ${ plugin.repo } --draft --title "Version ${ pluginVersion }" --notes "${ notes }" "${ pluginSlug }.zip"` );
}
