module.exports = {
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended', 'prettier' ],
	env: {
		browser: true,
		jquery: true,
		node: true,
		es6: true,
	},
	globals: {
		wp: true,
	},
	rules: {
		camelcase: 'warn',
		eqeqeq: 'warn',
		'no-console': 'warn',
		'@wordpress/no-unused-vars-before-return': 'off',
	},
};
