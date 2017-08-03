/**
 * External dependencies
 */
const findRoot = require( 'find-root' );
const fs = require( 'fs' );
const path = require( 'path' );
const pathIsInside = require( 'path-is-inside' );

const PROPKEY_ESNEXT = 'esnext';
const jsDir = path.resolve( __dirname, 'js' );
const nodeModulesDir = path.resolve( __dirname, 'node_modules' );

module.exports = {
	entry: './js/index.js',
	output: {
		filename: 'bundle.js',
		path: path.resolve( __dirname, 'dist' )
	},
	externals: {
		lodash: {
			commonjs: 'lodash',
			amd: 'lodash',
			root: '_' // indicates global variable
		},
		react: 'React',
		'react-dom': 'ReactDOM',
	},
	module: {
		rules: [
			{
				test: /\.jsx?$/,
				// Should Babel transpile the file at `filepath`?
				include: ( filepath ) => {
					return pathIsInside( filepath, jsDir ) ||
						( pathIsInside( filepath, nodeModulesDir ) &&
						hasPkgEsnext( filepath ) );
				},
				use: {
					loader: 'babel-loader'
				}
			}
		]
	},
	resolve: {
		extensions: [ '.js', '.jsx' ],
		mainFields: [ PROPKEY_ESNEXT, 'browser', 'module', 'main' ],
	},
};

/*
 * Find package.json for file at `filepath`.
 * Return `true` if it has a property whose key is `PROPKEY_ESNEXT` or 'module'.
 */
function hasPkgEsnext( filepath ) {
	const pkgRoot = findRoot( filepath );
	const packageJsonPath = path.resolve( pkgRoot, 'package.json' );
	const packageJsonText = fs.readFileSync( packageJsonPath,
		{ encoding: 'utf-8' } );
	const packageJson = JSON.parse( packageJsonText );
	return {}.hasOwnProperty.call( packageJson, PROPKEY_ESNEXT ) ||
		{}.hasOwnProperty.call( packageJson, 'module' );
}
