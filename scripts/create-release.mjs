#!/usr/bin/env zx

import 'zx/globals'

/**
 * Create the release once the release PR is merged.
 *
 * Config-driven, shared across the plugin family (WP Job Manager, WP Super
 * Cache, Crowdsignal Forms, Crowdsignal/Polldaddy). Per-repo specifics live in
 * `release.config.json`; this script is meant to stay identical everywhere.
 *
 * Runs in GitHub Actions after a `release/*` PR is merged: writes the changelog
 * from the (edited) PR body into the readme, tags, builds the zip, and creates
 * the GitHub release. WordPress.org SVN deployment is handled by the workflow
 * after this script runs.
 *
 * The workflow is unattended and can be re-run (re-merge, manual re-trigger),
 * so every mutating step is idempotent: it checks-then-acts and is safe to
 * repeat. If the tag and GitHub release already exist, it short-circuits so the
 * SVN step (which self-skips an existing tag) can still run.
 *
 * Usage: `node scripts/create-release.mjs <pr-number>`.
 *
 * External dependencies
 */
import fs from 'node:fs';
import process from 'node:process';
import { execSync } from 'node:child_process';

const REMOTE   = 'origin';
const cfg      = JSON.parse( fs.readFileSync( 'release.config.json', 'utf8' ) );
const buildDir = cfg.buildDir || 'build';

const pluginFileContents = fs.readFileSync( cfg.mainFile, 'utf8' );
const pluginVersion      = pluginFileContents.match( /Version: (.*)/ )[ 1 ].trim();
const pluginName         = pluginFileContents.match( /Plugin Name: (.*)/ )[ 1 ].trim();

const prNumber = process.argv[ 2 ];

// Parse (and validate) the release notes before any git write, so a malformed
// changelog fence aborts the run before it tags or ships anything.
const releaseNotes = getReleaseNotes();

// If the release already completed on a prior run, do nothing but emit the
// version so the SVN deploy step can self-skip the existing tag.
if ( remoteTagExists() && githubReleaseExists() ) {
	console.log( chalk.yellow( `Release ${ pluginVersion } is already tagged and published on GitHub — nothing to do.` ) );
	setWorkflowStepOutput();
	process.exit( 0 );
}

updateChangelog();
commitChangelog();
tagRelease();
buildPluginZip();
await createGithubRelease();
setWorkflowStepOutput();
await success();

/**
 * Extract the release notes from the release PR body. Throws an explicit error
 * (rather than a cryptic null dereference) if the `### Release Notes` / `---`
 * fences are missing or broken.
 *
 * @return {string} The release notes.
 */
function getReleaseNotes() {
	const prDescription = JSON.parse( execSync( `gh pr view ${ prNumber } -R ${ cfg.repo } --json body` ).toString() ).body;
	// Both fences must sit on their own lines, and the capture is GREEDY so it
	// runs to the LAST `---` line — the real closing fence. A non-greedy match
	// would stop at the first interior `---` (e.g. a Markdown horizontal rule in
	// the notes) and silently truncate the changelog. Greedy preserves that
	// content instead of dropping everything after it.
	const match         = prDescription.match( /### Release Notes\s*\n---\n([\S\s]*)\n---(?:\n|$)/ );
	if ( ! match ) {
		throw new Error(
			'Could not parse release notes from the PR body. Expected a "### Release Notes" ' +
			'heading followed by the changelog between two "---" fences. Check that both ' +
			'fences are intact in the release PR description.',
		);
	}
	return match[ 1 ].replace( /^- /gm, '* ' ).trim();
}

/**
 * @return {boolean} Whether the version tag already exists on the remote.
 */
function remoteTagExists() {
	return execSync( `git ls-remote --tags ${ REMOTE } refs/tags/${ pluginVersion }` ).toString().trim() !== '';
}

/**
 * @return {boolean} Whether a GitHub release for the version already exists.
 */
function githubReleaseExists() {
	try {
		execSync( `gh release view ${ pluginVersion } -R ${ cfg.repo }`, { stdio: 'ignore' } );
		return true;
	} catch {
		return false;
	}
}

/**
 * @return {boolean} Whether the readme already contains a `### <version>` entry.
 */
function changelogHasVersion() {
	const readme  = fs.readFileSync( cfg.readme, 'utf8' );
	const escaped = pluginVersion.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
	return new RegExp( `^### ${ escaped }\\b`, 'm' ).test( readme );
}

function updateChangelog() {
	// Idempotent: if the readme already has this version's entry (e.g. a prior
	// run committed it), don't prepend a duplicate.
	if ( changelogHasVersion() ) {
		console.log( chalk.yellow( `Changelog already contains ### ${ pluginVersion } — skipping.` ) );
		return;
	}

	// The readme's `== Changelog ==` section is the single source of truth.
	// Entries are `### <version> - <date>` blocks, newest first, trimmed to the
	// most recent few. The section is bounded by the next `== ... ==` heading or
	// end of file, so anything after it (e.g. Upgrade Notice) is preserved.
	const newEntry = `### ${ pluginVersion } - ${ new Date().toISOString().slice( 0, 10 ) }\n${ releaseNotes }`;

	let readme = fs.readFileSync( cfg.readme, 'utf8' );

	const replaced = readme.replace(
		/(== Changelog ==\n+)([\s\S]*?)(\n== |$)/,
		( _full, header, bodyBlock, boundary ) => {
			const body     = bodyBlock.trim();
			const existing = body ? body.split( /\n(?=### )/ ).map( ( entry ) => entry.trim() ) : [];
			const entries  = [ newEntry.trim(), ...existing ].slice( 0, 5 );
			const tail     = boundary === '\n== ' ? '\n\n== ' : '\n';
			return `${ header }${ entries.join( '\n\n' ) }${ tail }`;
		},
	);

	if ( replaced === readme ) {
		throw new Error( `Could not find the == Changelog == section in ${ cfg.readme }.` );
	}

	fs.writeFileSync( cfg.readme, replaced );
	console.log( chalk.bold( 'Adding new release to changelog: ' ) );
	console.log( newEntry );
	console.log( chalk.green( '✓' ), cfg.readme );
}

function commitChangelog() {
	execSync( `git add ${ cfg.readme }` );
	// Idempotent: only commit if the readme actually changed. `git diff --cached
	// --quiet` exits 0 when nothing is staged.
	try {
		execSync( 'git diff --cached --quiet' );
		console.log( chalk.yellow( 'No changelog changes to commit — skipping.' ) );
		return;
	} catch {
		// Non-zero exit means there are staged changes; fall through and commit.
	}
	execSync( `git commit -m "Update changelog for ${ pluginVersion }"` );
	execSync( `git push ${ REMOTE } HEAD` );
}

function tagRelease() {
	// Idempotent: skip if the tag is already on the remote.
	if ( remoteTagExists() ) {
		console.log( chalk.yellow( `Tag ${ pluginVersion } already on ${ REMOTE } — skipping tag.` ) );
		return;
	}
	const localTagExists = execSync( `git tag -l ${ pluginVersion }` ).toString().trim() !== '';
	if ( ! localTagExists ) {
		execSync( `git tag -a ${ pluginVersion } -m "Release ${ pluginVersion }"` );
	}
	execSync( `git push ${ REMOTE } ${ pluginVersion }` );
}

function buildPluginZip() {
	execSync( `make build 1> /dev/null` );
}

function setWorkflowStepOutput() {
	execSync( `echo "version=${ pluginVersion }" >> "$GITHUB_OUTPUT"` );
}

async function createGithubRelease() {
	// Idempotent: skip if a release for this version already exists.
	if ( githubReleaseExists() ) {
		console.log( chalk.yellow( `GitHub release ${ pluginVersion } already exists — skipping.` ) );
		return;
	}
	const pluginZip = `${ buildDir }/${ cfg.slug }.zip`;
	await $`gh release create ${ pluginVersion } -R ${ cfg.repo } --title ${ `Version ${ pluginVersion }` } --notes ${ releaseNotes } ${ pluginZip }`;
}

async function success() {
	console.log( chalk.bold.green( `✓ ${ pluginName } ${ pluginVersion } release created!` ) );
	const comment = `✅ **[${ pluginName } ${ pluginVersion } release](https://github.com/${ cfg.repo }/releases/tag/${ pluginVersion })** created!`;
	await $`gh pr comment ${ prNumber } -R ${ cfg.repo } --edit-last --body ${ comment }`;
}
