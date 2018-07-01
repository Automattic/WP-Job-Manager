/* global require, module, process, __dirname */

const CleanWebpackPlugin = require( 'clean-webpack-plugin' );
const LodashModuleReplacementPlugin = require( 'lodash-webpack-plugin' );
const UglifyJsPlugin = require( 'uglifyjs-webpack-plugin' );

const blockNames = [
	// Add the name of the block as a directory in the assets/blocks directory.
];

const webpackConfig = {
	entry: Object.assign(
		blockNames.reduce( ( blocks, blockName ) => {
			const path = `./assets/blocks/${ blockName }/index.jsx`;
			blocks[ blockName ] = path;
			return blocks;
		}, {} )
	),
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
