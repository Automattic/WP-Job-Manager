
const CleanWebpackPlugin = require('clean-webpack-plugin');
const LodashModuleReplacementPlugin = require('lodash-webpack-plugin');
const UglifyJsPlugin = require('uglifyjs-webpack-plugin');

const blockNames = [
	'jobs'
];

var webpack = require( 'webpack' ),
	webpackConfig = {
		mode: process.env.NODE_ENV === 'production' ? 'production' : 'development',

		entry: Object.assign(
			blockNames.reduce( ( blocks, blockName ) => {
				path = `./assets/blocks/${blockName}/index.jsx`;
				blocks[ blockName ] = path;
				return blocks;
			}, {} )
		),
		output: {
			filename: 'assets/blocks/build/[name].js',
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
						'css-loader'
					]
				},
				{
					test: /\.scss$/,
					use: [
						'style-loader',
						'css-loader',
						'sass-loader'
					]
				}
			],
		},
		plugins: [
			new CleanWebpackPlugin( [ 'assets/blocks/build' ] ),
			new LodashModuleReplacementPlugin(),
			new UglifyJsPlugin(),
		]
	};

module.exports = webpackConfig;
