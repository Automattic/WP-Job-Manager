/**
 * External dependencies
 */
import { config } from 'dotenv';
import fs from 'fs';
import process from 'process';
import inquirer from 'inquirer';
import { execSync } from 'child_process';

const PLUGINS = {
	'wp-job-manager': {
		file: 'wp-job-manager.php',
		constant: 'JOB_MANAGER_VERSION',
		repo: 'Automattic/wp-job-manager',
	},
};

/* eslint-disable no-console */

// Processes the .env variables.
config();

// // Assert git status if not disabled by env variable.
// if ( process.env.SENSEI_RELEASE_SKIP_REPOSITORY_CHECK !== 'true' ) {
// 	assertGitStatus();
// }

// Get plugin information.
const pluginSlug         = getPluginSlug();
const plugin             = PLUGINS[ pluginSlug ];
const pluginFileName     = plugin.file;
const pluginFileContents = readFileContents( pluginFileName );
const pluginVersion      = pluginFileContents.match( /Version: (.*)/ )[ 1 ];
const pluginName         = pluginFileContents.match( /Plugin Name: (.*)/ )[ 1 ];

const version = process.argv[ 3 ];

//
// const changelog = generateChangelog( version );
// console.log( changelog );
// createPR( version, changelog );
//
// process.exit();

// Confirm versions through CLI.
if (
	! ( await askForConfirmationOnVersionsInformation(
		version,
		pluginFileContents,
	) )
) {
	console.log( 'Aborted!' );
	process.exit( 0 );
}

// Create release branch.
const { originalBranchName, releaseBranchName } = createReleaseBranch(
	pluginSlug,
	version,
);
try {
	// Add changes to release branch.
	setNewPluginVersion(
		pluginFileName,
		pluginFileContents,
		version,
	);
	replaceNextVersionPlaceholder( version );
	// TODO Update the readme.txt and commit (Sensei LMS only).
	updatePackageJsonFiles( version );
	generatePotFiles();
	const changelog = generateChangelog();

	// Create PR
	pushBranch( releaseBranchName );
	createPR( version, changelog );

} catch ( error ) {
	revertOnError( originalBranchName, releaseBranchName );
	console.log( `\n\nORIGINAL ERROR: '${ error.name }'` );
	console.log( error.message );
}

/*
 * HELPER FUNCTIONS FROM HERE.
 */

/**
 * Check that we are in the default branch and there are no uncommitted changes.
 * If check is not successful an error will be thrown finishing the execution.
 */
function assertGitStatus() {
	const defaultBranchName = execSync(
		'basename $(git symbolic-ref --short refs/remotes/origin/HEAD)',
	)
		.toString()
		.trim();
	const currentBranchName = execSync( 'git branch --show-current' )
		.toString()
		.trim();
	if ( currentBranchName !== defaultBranchName ) {
		throw new Error(
			`Release script must only be run while on '${ defaultBranchName }' branch. You are on '${ currentBranchName }'.`,
		);
	}
	const gitStatus = execSync( 'git status -suno\n' ).toString().trim();
	if ( gitStatus !== '' ) {
		throw new Error(
			`Uncommitted changes detected in your repository! Please ensure you are in a clean status before releasing.`,
		);
	}
}

/**
 * Get plugin slug from command arguments.
 * Throws an error if invalid.
 *
 * @return {string} The plugin slug.
 */
function getPluginSlug() {
	const slug = process.argv[ 2 ];
	if ( ! ( slug in PLUGINS ) ) {
		throw new Error(
			'Please provide a valid plugin slug as the first parameter: ' +
			Object.keys( PLUGINS ).join( ', ' ),
		);
	}
	return slug;
}

/**
 * Return file contents given the filepath.
 * This method will throw an error if the file does not exist.
 *
 * @param {string} filepath The file path to be read.
 * @return {string} The file contents.
 */
function readFileContents( filepath ) {
	let contents = false;
	try {
		contents = fs.readFileSync( filepath, 'utf8' );
	} catch ( err ) {
		throw new Error( `File (${ filepath }) could not be read.` );
	}

	return contents;
}

/**
 * Ask for confirmation on new version and dependency versions through the CLI.
 *
 * @param {string} newVersion   The new version.
 * @param {string} fileContents The current contents of the main plugin file.
 * @return {boolean} Whether the confirmation was accepted or not.
 */
async function askForConfirmationOnVersionsInformation(
	newVersion,
	fileContents,
) {
	// WP Versions
	const currentWPRequiresAtLeast = fileContents.match(
		/Requires at least: (.*)/,
	)[ 1 ];
	const currentWPTestedUpTo      = fileContents.match( /Tested up to: (.*)/ )[ 1 ];
	// PHP
	const currentRequiresPhp       = fileContents.match( /Requires PHP: (.*)/ )[ 1 ];
	// WC
	// const currentWCRequiresAtLeast = fileContents.match(
	// 	/WC requires at least: (.*)/,
	// )[ 1 ] ?? ' - ';
	// const currentWCTestedUpTo      = fileContents.match(
	// 	/WC tested up to: (.*)/,
	// )[ 1 ] ?? ' - ';

	// Display all versioning information and ask for confirmation.
	console.log( 'New release version:' );
	console.log( `Version: ${ newVersion }` );
	console.log( '---' );
	console.log( `(WP)  Requires at least: ${ currentWPRequiresAtLeast }` );
	console.log( `(WP)  Tested up to: ${ currentWPTestedUpTo }` );
	console.log( `(PHP) Requires PHP: ${ currentRequiresPhp }` );
	// console.log( `(WC)  WC requires at least: ${ currentWCRequiresAtLeast }` );
	// console.log( `(WC)  WC tested up to: ${ currentWCTestedUpTo }` );
	// const { confirmation } = await inquirer.prompt( {
	// 	type: 'confirm',
	// 	name: 'confirmation',
	// 	message: 'Is the above information correct?',
	// 	default: false,
	// } );
	// return confirmation;
	return true;
}

/**
 * Create release branch given the slug and version.
 *
 * @param {string} slug    Plugin slug name.
 * @param {string} version New version.
 * @return {Object} The name of the original and the new release branches.
 */
function createReleaseBranch( slug, version ) {
	const currentBranchName = execSync( 'git branch --show-current' )
		.toString()
		.trim();
	const branchName        = `release/${ slug }-${ version }`;
	console.log( `Creating branch '${ branchName }' ...` );
	try {
		execSync( `git checkout -b ${ branchName }` );
	} catch {
		throw new Error(
			'Error creating branch. Check branch does not exist.',
		);
	}
	return {
		originalBranchName: currentBranchName,
		releaseBranchName: branchName,
	};
}

/**
 * Set new version in the main plugin file.
 * This method also creates a commit in the current branch.
 *
 * @param {string} filename     The path to the main plugin file.
 * @param {string} fileContents The contents of the main plugin file.
 * @param {string} version      The new version to be released.
 */
function setNewPluginVersion( filename, fileContents, version ) {
	console.log( 'Updating plugin file versions ...' );
	let newPluginFileContents = fileContents.replace(
		/Version: (.*)/,
		`Version: ${ version }`,
	);

	// Update version constant.
	const { constant }    = PLUGINS[ pluginSlug ];
	newPluginFileContents = newPluginFileContents.replace(
		new RegExp( `define\( '${constant}', '.*' \);` ),
		`define( '${ constant }', '${ version }' );`,
	);

	fs.writeFileSync( filename, newPluginFileContents, 'utf-8' );
	execSync(
		`git add ${ filename } && git commit -m "Update plugin file versions."`,
	);
}

/**
 * Replaces the next-version placeholder with the new version to be released.
 * This method also creates a commit in the current branch.
 *
 * @param {string} version The new version.
 */
function replaceNextVersionPlaceholder( version ) {
	console.log( `Replacing next version placeholder with ${ version } ...` );
	execSync( `bash scripts/replace-next-version-tag.sh ${ version }` );
	execSync(
		`git add . && git commit --allow-empty -m "Replace next version placeholders."`,
	);
}

/**
 * Update package.json and package-lock.json files with the new version to be released.
 * This method also creates a commit in the current branch.
 *
 * @param {string} version The new version.
 */
function updatePackageJsonFiles( version ) {
	console.log( 'Updating package.json version...' );
	try {
		execSync( `npm version ${ version } --no-git-tag-version` );
	} catch {
		throw new Error( 'Version could not be updated in package.json file.' );
	}

	execSync(
		`git add package.json package-lock.json && git commit -m "Update package.json versions."`,
	);
}

/**
 * Generate POT files (translations).
 * This method also creates a commit in the current branch.
 */
function generatePotFiles() {
	console.log( 'Updating POT files...' );
	try {
		execSync( `npm run i18n:build 2> /dev/null` );
	} catch {
		throw new Error( 'POT file generation failed.' );
	}
	execSync( `git add languages/ && git commit -m "Generate pot files."` );
}

/**
 * Generates the changelog using the changelogger script.
 * This method also creates a commit in the current branch.
 */
function generateChangelog( version ) {
	const search = `milestone:${ version }`;
	let prs      = execSync(
		`gh pr list --state all --base trunk --search "${ search }" --json number,title,body,labels`,
	);
	prs          = JSON.parse( prs );

	const changelogs = prs.map( ( pr ) => {
		const body         = pr.body;
		let changelogIndex = body.indexOf( "### Changelog" );
		if ( changelogIndex === -1 ) {
			// Fall back to PR title.
			return `* ${ pr.title } (#${ pr.number })`;
		}
		changelogIndex += "### Changelog".length;
		const nextSectionIndex  = body.indexOf( "###", changelogIndex + 1 );
		const changelogEndIndex =
			      nextSectionIndex === -1 ? body.length : nextSectionIndex;
		return body.substring( changelogIndex, changelogEndIndex );
	} );

	return changelogs.join( "\n" );
}

/**
 * Create release PR.
 *
 * @param version
 * @param changelog
 */
function createPR( version, changelog ) {
	let body = `

### Changelog

${ changelog }

### Hooks, templates

...

### Release

- [ ] Click 'Ready for review' if everything looks right.
- [ ] Plugin zip built.
- [ ] New version deployed at test site.
- [ ] Merge PR.
- [ ] GH release tag created.
- [ ] Plugin pushed to WordPress.org
- [ ] WPJobManager.com release created.
- [ ] P2 release post created.


`;
	body     = body.replace( '"', '\"' );

	execSync( `gh pr create -R ${ plugin.repo } --assignee @me --base trunk --draft --title "Release ${ version }" --label "[Type] Maintenance" --label "Release" --body "${ body }"  2> /dev/null` );
}

/**
 * Pushes release branch.
 *
 * @param {string} branch The release branch name.
 */
function pushBranch( branch ) {
	console.log( 'Pushing branch ...' );
	try {
		execSync( `git push origin ${ branch } 2> /dev/null` );
	} catch {
		throw Error( `New branch '${ branch }' could not be pushed.` );
	}
}

/**
 * Revert workspace to original status.
 *
 * @param {string} originalBranch The original branch name.
 * @param {string} releaseBranch  The new release branch name.
 */
function revertOnError( originalBranch, releaseBranch ) {
	console.log( '❌ ERROR!' );
	console.log( 'Trying to move back to previous branch...' );
	execSync( `git checkout . && git checkout ${ originalBranch }` );
	console.log( `Deleting '${ releaseBranch }'....` );
	execSync( `git branch -D ${ releaseBranch }` );
}
