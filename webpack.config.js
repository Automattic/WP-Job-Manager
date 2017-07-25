var path = require( 'path' );

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
				exclude: /node_modules/,
				use: {
					loader: 'babel-loader'
				}
			}
		]
	}
};
