/* global require, module, process, __dirname */
const CleanWebpackPlugin = require( 'clean-webpack-plugin' );
const LodashModuleReplacementPlugin = require( 'lodash-webpack-plugin' );
const miniCssExtractPlugin = require( 'mini-css-extract-plugin' );
const glob = require( 'glob' );
const entryArray = glob.sync( './assets/blocks/**/index.jsx' );
const entryObject = entryArray.reduce( ( acc, item ) => {
	let name = item.replace( './assets/blocks/', '' ).replace( '/index.jsx', '' );
	acc[name] = item;

	return acc;
}, {} );

const webpackConfig = ( env, argv ) => {
	return {
		entry: entryObject,
		output: {
			filename: 'build/[name]/index.js',
			path: __dirname,
		},
		module: {
			rules: [
				{
					test: /.jsx$/,
					use: 'babel-loader',
					exclude: /node_modules/,
				},
				{
					test: /\.scss$/,
					include: [
						/assets\/blocks/
					],
					use: [
						argv.mode !== 'production' ? 'style-loader' : miniCssExtractPlugin.loader,
						'css-loader',
						'sass-loader'
					]
				},
			],
		},
		plugins: [
			new CleanWebpackPlugin( [ 'build' ] ),
			new LodashModuleReplacementPlugin(),
			new miniCssExtractPlugin( {
				filename: 'build/[name]/style.css'
			} ),
		],
	};
};

module.exports = webpackConfig;
