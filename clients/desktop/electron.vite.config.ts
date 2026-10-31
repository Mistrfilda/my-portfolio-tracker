import { defineConfig } from 'electron-vite';
import { rendererConfig } from './vite.renderer.config';

export default defineConfig(({ command }) => ({
	main: {
		build: {
			externalizeDeps: true,
		},
	},
	preload: {
		build: {
			externalizeDeps: false,
			rolldownOptions: {
				output: {
					format: 'cjs',
					entryFileNames: 'index.cjs',
					codeSplitting: false,
				},
			},
		},
	},
	renderer: rendererConfig(command === 'serve'),
}));
