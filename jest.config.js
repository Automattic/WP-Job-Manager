module.exports = {
	preset: '@wordpress/jest-preset-default',
	testMatch: [ '**/assets/**/tests/**/*.js?(x)' ],
	testPathIgnorePatterns: [ 'tmp', 'node_modules' ],
};
