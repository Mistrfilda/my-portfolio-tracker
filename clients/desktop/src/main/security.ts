import { isAbsolute, relative, resolve } from 'node:path';

export const APP_URL = 'app://portfolio/index.html';

export function resolveRendererFile(
	requestUrl: string,
	root: string,
): string | null {
	try {
		const url = new URL(requestUrl);
		if (
			url.protocol !== 'app:' ||
			url.host !== 'portfolio' ||
			url.username ||
			url.password
		) {
			return null;
		}

		const pathname = decodeURIComponent(url.pathname);
		if (pathname.includes('\0') || pathname.includes('\\')) {
			return null;
		}

		const file = resolve(
			root,
			'.' + (pathname === '/' ? '/index.html' : pathname),
		);
		const relativePath = relative(root, file);
		if (
			relativePath.startsWith('..') ||
			isAbsolute(relativePath) ||
			relativePath === ''
		) {
			return null;
		}
		return file;
	} catch {
		return null;
	}
}

export function isTrustedRendererUrl(
	url: string,
	developmentUrl?: string,
): boolean {
	return url === (developmentUrl ?? APP_URL);
}

export function validateDevelopmentUrl(value: string): string {
	const url = new URL(value);
	if (
		url.protocol !== 'http:' ||
		url.hostname !== '127.0.0.1' ||
		url.port !== '5173' ||
		url.pathname !== '/' ||
		url.username ||
		url.password ||
		url.search ||
		url.hash
	) {
		throw new Error(
			'The desktop development server must use http://127.0.0.1:5173/.',
		);
	}
	return url.href;
}
