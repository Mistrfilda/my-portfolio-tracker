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
			rollupOptions: {
				output: {
					format: 'cjs',
					entryFileNames: 'index.cjs',
					inlineDynamicImports: true,
				},
			},
		},
	},
	renderer: rendererConfig(command === 'serve'),
}));
