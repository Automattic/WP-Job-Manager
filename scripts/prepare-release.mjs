/**
 * Prepare a release PR.
 *
 * Config-driven port of the WP Job Manager release tooling. Reads
 * `release.config.json` at the repo root; per-repo specifics live there, this
 * script is intended to stay identical across plugins.
 *
 * Usage: `make release VERSION=x.y.z` (which runs `node scripts/prepare-release.mjs x.y.z`).
 *
 * External dependencies
 */
import { config as loadDotenv } from 'dotenv';
import fs from 'fs';
import os from 'node:os';
import path from 'node:path';
import process from 'node:process';
import inquirer from 'inquirer';
import chalk from 'chalk';
import { execSync } from 'node:child_process';
import prTemplate from './RELEASE_PR_TEMPLATE.md.mjs';

const REMOTE = 'origin';
const BASE_BRANCH = 'trunk';

/* eslint-disable no-console */

// Processes the .env variables.
loadDotenv();

// Load per-repo configuration.
const cfg = JSON.parse( readFileContents( 'release.config.json' ) );

const pluginFileContents = readFileContents( cfg.mainFile );
const pluginName         = pluginFileContents.match( /Plugin Name: (.*)/ )[ 1 ].trim();
const version            = process.argv[ 2 ];

if ( ! version ) {
	console.log( chalk.bold.red( 'Error: VERSION is required. Usage: make release VERSION=x.y.z' ) );
	process.exit( 1 );
}

const ghPrs = `gh pr list -R ${ cfg.repo } --state merged --base ${ BASE_BRANCH } --limit 500 --search "milestone:${ version }"`;

// Confirm release through CLI.
if ( ! ( await askForConfirmation( version, pluginFileContents ) ) ) {
	process.exit( 0 );
}

// Create release branch.
const { originalBranchName, releaseBranch } = createReleaseBranch( cfg.slug, version );

try {
	updateVersionInFile( cfg.mainFile );
	updateVersionInFile( cfg.readme );
	replaceNextVersionPlaceholder();
	updatePackageJsonFiles();
	generatePotFiles();
	commitFiles();

	const changelog = buildReleaseNotes();

	// Create PR
	pushBranch();
	createPR( changelog );
} catch ( error ) {
	console.log( chalk.bold.red( error.message ) );
	console.log( error.stack );

	const { confirmation } = await inquirer.prompt( {
		type: 'confirm',
		name: 'confirmation',
		message: 'Roll back and delete release branch?',
		default: true,
	} );

	if ( confirmation ) {
		revertOnError( originalBranchName, releaseBranch );
	}
}

/**
 * Return file contents given the filepath. Throws if the file does not exist.
 *
 * @param {string} filepath The file path to be read.
 * @return {string} The file contents.
 */
function readFileContents( filepath ) {
	try {
		return fs.readFileSync( filepath, 'utf8' );
	} catch ( err ) {
		throw new Error( `File (${ filepath }) could not be read.` );
	}
}

/**
 * Ask for confirmation on the new version through the CLI.
 *
 * @param {string} newVersion   The new version.
 * @param {string} fileContents The current contents of the main plugin file.
 * @return {Promise<boolean>} Whether the confirmation was accepted or not.
 */
async function askForConfirmation( newVersion, fileContents ) {
	const wpRequiresAtLeast = ( fileContents.match( /Requires at least: (.*)/ ) || [] )[ 1 ];
	const wpTestedUpTo      = ( fileContents.match( /Tested up to: (.*)/ ) || [] )[ 1 ];
	const requiresPhp       = ( fileContents.match( /Requires PHP: (.*)/ ) || [] )[ 1 ];

	console.log( `🚀 Preparing new release:`, chalk.bold( `${ cfg.slug } ${ newVersion }` ) );
	console.log( `-----------------------------` );
	console.log( chalk.bold( '📦 Plugin header:' ) );
	console.log( `   Version:`, chalk.bold.green( newVersion ) );
	if ( wpRequiresAtLeast ) console.log( `   (WP)  Requires at least:`, chalk.bold( wpRequiresAtLeast ) );
	if ( wpTestedUpTo ) console.log( `   (WP)  Tested up to:`, chalk.bold( wpTestedUpTo ) );
	if ( requiresPhp ) console.log( `   (PHP) Requires PHP:`, chalk.bold( requiresPhp ) );
	console.log( `-----------------------------` );
	console.log( `ℹ️️  Make sure a ` + chalk.bold( `milestone ${ newVersion }` ) + ` exists on GitHub, and all PRs are assigned to it.` );
	console.log( `-----------------------------` );
	console.log( `ℹ️️  Make sure you are logged in to GH CLI with \`gh auth login\`.` );
	// Scope the auth check to github.com; an unrelated host in `gh` config would
	// otherwise make `gh auth status` exit non-zero and abort the release.
	execSync( 'gh auth status --hostname github.com' );
	console.log( `-----------------------------` );
	console.log( `Pull requests to include (milestone ${ newVersion }):` );

	execSync( ghPrs, { stdio: 'inherit' } );

	const branch  = execSync( 'git branch --show-current' ).toString().trim();
	const warning = ( branch !== BASE_BRANCH ) ? chalk.bgRed( ` ‼️  Not ${ BASE_BRANCH }! ‼️ ` ) : '';

	console.log( `-----------------------------` );
	console.log( 'Branch:', chalk.bold[ branch !== BASE_BRANCH ? 'red' : 'green' ]( branch ), warning );
	console.log( `-----------------------------` );

	const { confirmation } = await inquirer.prompt( {
		type: 'confirm',
		name: 'confirmation',
		message: 'Proceed with release preparation?',
		default: false,
	} );
	return confirmation;
}

/**
 * Create release branch given the slug and version.
 *
 * @param {string} slug       Plugin slug name.
 * @param {string} newVersion New version.
 * @return {Object} The name of the original and the new release branches.
 */
function createReleaseBranch( slug, newVersion ) {
	const currentBranchName = execSync( 'git branch --show-current' ).toString().trim();
	const branchName        = `release/${ slug }-${ newVersion }`;
	console.log( `Creating branch '${ branchName }' ...` );
	try {
		execSync( `git checkout -b ${ branchName }` );
	} catch {
		throw new Error( 'Error creating branch. Check branch does not exist.' );
	}
	return { originalBranchName: currentBranchName, releaseBranch: branchName };
}

/**
 * Set the new version in a file (`Version:`/`Stable tag:` header and, if
 * configured, a version constant). Stages the file.
 *
 * @param {string} filename The path to the file.
 */
function updateVersionInFile( filename ) {
	console.log( `Updating version in ${ filename } ...` );
	let contents = readFileContents( filename ).replace(
		/(Version|Stable tag): (.*)/,
		`$1: ${ version }`,
	);

	if ( cfg.versionConstant ) {
		contents = contents.replace(
			new RegExp( `define\\( '${ cfg.versionConstant }', '.*' \\);` ),
			`define( '${ cfg.versionConstant }', '${ version }' );`,
		);
	}

	fs.writeFileSync( filename, contents, 'utf-8' );
	execSync( `git add ${ filename }` );
}

/**
 * Replace the next-version placeholder with the new version, if the repo uses it.
 */
function replaceNextVersionPlaceholder() {
	if ( ! cfg.nextVersionPlaceholder ) {
		return;
	}
	console.log( `Replacing next version placeholder with ${ version } ...` );
	execSync( `bash scripts/replace-next-version-tag.sh ${ version }` );
	execSync( `git add -u` );
}

/**
 * Update package.json/package-lock.json, if the repo tracks the plugin version there.
 */
function updatePackageJsonFiles() {
	if ( ! cfg.bumpPackageJson ) {
		return;
	}
	console.log( 'Updating package.json version...' );
	try {
		execSync( `npm version ${ version } --no-git-tag-version --allow-same-version` );
		execSync( `git add package.json` );
		// Stage whichever lockfile the repo actually uses. `git add` on a missing
		// pathspec aborts and stages nothing, so only add lockfiles that exist —
		// this keeps the script working for npm (package-lock.json) and pnpm
		// (pnpm-lock.yaml) repos alike.
		for ( const lockfile of [ 'package-lock.json', 'pnpm-lock.yaml', 'npm-shrinkwrap.json' ] ) {
			if ( fs.existsSync( lockfile ) ) {
				execSync( `git add ${ lockfile }` );
			}
		}
	} catch {
		console.log( 'Version could not be updated in package.json file.' );
	}
}

/**
 * Regenerate translation files via `make i18n` (a no-op where unused) and stage them.
 */
function generatePotFiles() {
	console.log( 'Updating translations (make i18n)...' );
	try {
		execSync( `make i18n 2> /dev/null` );
	} catch {
		throw new Error( 'POT file generation failed.' );
	}
	if ( fs.existsSync( 'languages' ) ) {
		execSync( `git add languages/` );
	}
}

function commitFiles() {
	execSync( `git commit -m "Update plugin to ${ version }."` );
}

/**
 * Generate the changelog from the milestone's merged PRs.
 *
 * @return {string} The assembled changelog.
 */
function buildReleaseNotes() {
	const prs = JSON.parse( execSync( `${ ghPrs } --json number,title,body,labels` ) );

	let changelog = prs.map( ( pr ) => {
		const changelogSections = ( pr.body || '' ).match( /### Release Notes([\S\s]*?)(?:\n#{1,6} |\n<!--|$)/ );

		if ( ! changelogSections ) {
			return `* ${ pr.title } (#${ pr.number })`;
		}
		const prChangelog = changelogSections[ 1 ].trim();
		if ( ! prChangelog.match( /\w/ ) ) {
			return '';
		}
		return prChangelog;
	} ).join( '\n' );

	if ( changelog.trim().length === 0 ) {
		changelog = '* Updated plugin headers';
	}

	console.log( 'Proposed changelog: ' );
	console.log( changelog );

	return changelog;
}

/**
 * Create the release PR.
 *
 * @param {string} changelog The assembled changelog.
 */
function createPR( changelog ) {
	const title = `Release ${ pluginName } ${ version }`;
	const body  = prTemplate( { changelog, version, pluginName } );

	// Pass the body via a temp file so backticks, quotes and other shell
	// metacharacters inside changelog entries can't be interpreted by the shell.
	const bodyFile = path.join( os.tmpdir(), `release-pr-body-${ cfg.slug }-${ version }.md` );
	fs.writeFileSync( bodyFile, body, 'utf-8' );

	try {
		const prLink = execSync( `gh pr create -R ${ cfg.repo } -B ${ BASE_BRANCH } -H ${ releaseBranch } --assignee @me --title "${ title }" --body-file "${ bodyFile }"` );
		execSync( `open ${ prLink }` );
		console.log( `PR: ${ prLink }` );
	} finally {
		fs.unlinkSync( bodyFile );
	}
}

/**
 * Push the release branch.
 */
function pushBranch() {
	console.log( 'Pushing branch ...' );
	try {
		execSync( `git push -u ${ REMOTE } ${ releaseBranch }` );
	} catch {
		throw Error( `New branch '${ releaseBranch }' could not be pushed.` );
	}
}

/**
 * Revert the workspace to its original state.
 *
 * @param {string} originalBranch The original branch name.
 * @param {string} branchToDelete The release branch to delete.
 */
function revertOnError( originalBranch, branchToDelete ) {
	console.log( 'Trying to move back to previous branch...' );
	execSync( `git checkout . && git checkout ${ originalBranch }` );
	console.log( `Deleting '${ branchToDelete }'....` );
	execSync( `git branch -D ${ branchToDelete }` );
}
