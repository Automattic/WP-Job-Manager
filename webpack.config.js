/* global require, module, process, __dirname */
const CleanWebpackPlugin = require( 'clean-webpack-plugin' );
const LodashModuleReplacementPlugin = require( 'lodash-webpack-plugin' );
const UglifyJsPlugin = require( 'uglifyjs-webpack-plugin' );
const glob = require( 'glob' );
const entryArray = glob.sync( './assets/blocks/**/index.jsx' );
const entryObject = entryArray.reduce( ( acc, item ) => {
	let name = item.replace( './assets/blocks/', '' ).replace( '/index.jsx', '' );
	acc[name] = item;

	return acc;
}, {} );

const webpackConfig = {
	entry: entryObject,
	output: {
		filename: 'assets/build/blocks/[name].js',
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
				test: /\.css$/,
				use: [
					'style-loader',
					'css-loader',
				],
			},
			{
				test: /\.scss$/,
				use: [
					'style-loader',
					'css-loader',
					'sass-loader',
				],
			},
		],
	},
	plugins: [
		new CleanWebpackPlugin( [ 'assets/build/blocks' ] ),
		new LodashModuleReplacementPlugin(),
		new UglifyJsPlugin(),
	],
};

module.exports = webpackConfig;
