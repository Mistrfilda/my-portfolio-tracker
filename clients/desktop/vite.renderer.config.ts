import { fileURLToPath } from 'node:url';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { defineConfig, type UserConfig } from 'vite';

export function rendererConfig(development: boolean): UserConfig {
	const policy = [
		"default-src 'self'",
		"script-src 'self'" + (development ? " 'unsafe-inline'" : ''),
		"style-src 'self' 'unsafe-inline'",
		"img-src 'self' data:",
		"font-src 'self'",
		"connect-src 'self'" + (development ? ' ws://127.0.0.1:5173' : ''),
		"object-src 'none'",
		"base-uri 'self'",
		"form-action 'none'",
	].join('; ');

	return {
		root: fileURLToPath(new URL('./src/renderer', import.meta.url)),
		base: './',
		plugins: [
			react(),
			tailwindcss(),
			{
				name: 'desktop-content-security-policy',
				transformIndexHtml: () => [
					{
						tag: 'meta',
						attrs: { 'http-equiv': 'Content-Security-Policy', content: policy },
						injectTo: 'head-prepend',
					},
				],
			},
		],
		resolve: {
			alias: {
				'@': fileURLToPath(new URL('./src/renderer/src', import.meta.url)),
			},
		},
		server: {
			host: '127.0.0.1',
			port: 5173,
			strictPort: true,
		},
		build: {
			outDir: fileURLToPath(new URL('./out/renderer', import.meta.url)),
			emptyOutDir: true,
			minify: 'esbuild',
		},
	};
}

export default defineConfig(({ command }) =>
	rendererConfig(command === 'serve'),
);
