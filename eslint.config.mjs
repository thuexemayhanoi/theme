import js from '@eslint/js';
import globals from 'globals';

export default [
	{ ignores: ['vendor/**', 'node_modules/**', 'dist/**', 'reports/**', 'playwright-report/**', 'test-results/**', 'plugins/**', 'tools/sandbox/**'] },
	js.configs.recommended,
	{
		files: ['assets/js/**/*.js'],
		languageOptions: { ecmaVersion: 2022, sourceType: 'script', globals: { ...globals.browser } },
		rules: { 'no-var': 'off', 'no-unused-vars': ['error', { args: 'none' }] },
	},
	{
		files: ['tools/**/*.mjs', 'tests/**/*.mjs', 'tests/**/*.js', 'eslint.config.mjs', 'playwright.config.mjs'],
		languageOptions: { ecmaVersion: 2023, sourceType: 'module', globals: { ...globals.node, ...globals.browser } },
		rules: { 'no-unused-vars': ['error', { args: 'none', varsIgnorePattern: '^_' }] },
	},
];
