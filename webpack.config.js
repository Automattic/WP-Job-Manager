const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

const files = {
	'js/admin': 'js/admin.js',
	'js/ajax-file-upload': 'js/ajax-file-upload.js',
	'js/ajax-filters': 'js/ajax-filters.js',
	'js/datepicker': 'js/datepicker.js',
	'js/job-application': 'js/job-application.js',
	'js/job-dashboard': 'js/job-dashboard.js',
	'js/job-submission': 'js/job-submission.js',
	'js/multiselect': 'js/multiselect.js',
	'js/term-multiselect': 'js/term-multiselect.js',
	'css/admin': 'css/admin.scss',
	'css/frontend': 'css/frontend.scss',
	'css/job-listings': 'css/job-listings.scss',
	'css/job-submission': 'css/job-submission.scss',
	'css/setup': 'css/setup.scss',
	'css/menu': 'css/menu.scss',
};

const baseDist = 'assets/dist/';

Object.keys( files ).forEach( function ( key ) {
	files[ key ] = path.resolve( './assets', files[ key ] );
} );

const FileLoader = {
	test: /\.(?:gif|jpg|jpeg|png|svg|woff|woff2|eot|ttf|otf)$/i,
	loader: 'file-loader',
	options: {
		name: '[path][name]-[contenthash].[ext]',
		context: 'assets',
		publicPath: '..',
	},
};

module.exports = {
	...defaultConfig,
	entry: files,
	output: {
		path: path.resolve( '.', baseDist ),
	},
	module: {
		rules: [ FileLoader, ...defaultConfig.module.rules.slice(0, 4) ],
	},
};
