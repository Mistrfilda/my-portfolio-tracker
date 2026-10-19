import { describe, expect, it } from 'vitest';
import {
	APP_URL,
	isTrustedRendererUrl,
	resolveRendererFile,
	validateDevelopmentUrl,
} from '../../src/main/security';

describe('packaged renderer assets', () => {
	const root = '/application/out/renderer';

	it('serves only files inside the renderer directory', () => {
		expect(resolveRendererFile(APP_URL, root)).toBe(root + '/index.html');
		expect(resolveRendererFile('app://portfolio/assets/main.js', root)).toBe(
			root + '/assets/main.js',
		);
		expect(resolveRendererFile('app://portfolio/', root)).toBe(
			root + '/index.html',
		);
	});

	it.each([
		'app://portfolio/%2e%2e%2fmain/index.js',
		'app://portfolio/assets/%2e%2e%2f%2e%2e%2fmain/index.js',
		'app://portfolio/%5c..%5cmain/index.js',
		'app://portfolio/%00',
		'app://portfolio/%invalid',
		'app://attacker/index.html',
		'app://portfolio:1234/index.html',
		'app://user@portfolio/index.html',
		'https://portfolio/index.html',
		'file:///application/out/main/index.js',
	])('rejects untrusted asset requests: %s', (url) => {
		expect(resolveRendererFile(url, root)).toBeNull();
	});
});

describe('renderer origins', () => {
	it('accepts exactly the running application entry point', () => {
		expect(isTrustedRendererUrl(APP_URL)).toBe(true);
		expect(isTrustedRendererUrl('app://portfolio/other.html')).toBe(false);
		expect(isTrustedRendererUrl('https://portfolio/index.html')).toBe(false);
		expect(isTrustedRendererUrl('http://127.0.0.1:5173/')).toBe(false);
	});

	it('accepts only the configured local development page in development', () => {
		const devUrl = validateDevelopmentUrl('http://127.0.0.1:5173');
		expect(isTrustedRendererUrl(devUrl, devUrl)).toBe(true);
		expect(isTrustedRendererUrl(APP_URL, devUrl)).toBe(false);
		expect(isTrustedRendererUrl('http://127.0.0.1:5174/', devUrl)).toBe(false);
	});

	it.each([
		'https://example.com/',
		'http://127.0.0.1:5173.attacker.test/',
		'http://127.0.0.1:5174/',
		'http://user@127.0.0.1:5173/',
		'http://127.0.0.1:5173/other',
		'http://127.0.0.1:5173/?redirect=outside',
	])('rejects unexpected development locations: %s', (url) => {
		expect(() => validateDevelopmentUrl(url)).toThrow();
	});
});
