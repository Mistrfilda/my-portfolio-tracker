import js from '@eslint/js';
import { defineConfig, globalIgnores } from 'eslint/config';
import hooks from 'eslint-plugin-react-hooks';
import refresh from 'eslint-plugin-react-refresh';
import tseslint from 'typescript-eslint';

export default defineConfig([
	globalIgnores([
		'node_modules/**',
		'out/**',
		'artifacts/**',
		'test-results/**',
		'playwright-report/**',
		'coverage/**',
	]),
	js.configs.recommended,
	tseslint.configs.recommended,
	{
		files: ['src/renderer/src/**/*.{ts,tsx}'],
		extends: [hooks.configs.flat.recommended, refresh.configs.vite],
		rules: {
			'no-restricted-imports': [
				'error',
				{
					patterns: [
						{
							group: ['electron', 'node:*'],
							message: 'Use the typed preload bridge from the renderer.',
						},
						{
							group: ['**/main/**', '**/preload/**'],
							message: 'Renderer code may import shared contracts only.',
						},
					],
				},
			],
		},
	},
	{
		files: ['src/renderer/src/components/ui/**/*.tsx'],
		rules: {
			'react-refresh/only-export-components': 'off',
		},
	},
]);
