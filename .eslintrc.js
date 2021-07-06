module.exports = {
	extends: [
		'@nextcloud',
	],
	rules: {
		// Allow shallow import of @vue/test-utils in order to be able to use it in
		// the src folder
		'node/no-unpublished-import': ['error', {
			allowModules: ['@vue/test-utils', '@testing-library/vue'],
		}],
		'valid-jsdoc': 'off',
	},
}
