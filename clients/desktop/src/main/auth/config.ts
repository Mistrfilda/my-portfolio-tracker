import { createHash } from 'node:crypto';
import bundledConnection from './connection.json' with { type: 'json' };

export interface Connection {
	name: string;
	serverUrl: string;
	clientId: string;
	scopes: string[];
	storageKey: string;
	authorizationUrl: string;
	tokenUrl: string;
	revocationUrl: string;
	userUrl: string;
}

export function parseConnection(value: unknown): Connection {
	if (typeof value !== 'object' || value === null)
		throw new Error('Invalid connection configuration.');
	const data = value as Record<string, unknown>;
	if (
		typeof data.name !== 'string' ||
		!data.name.trim() ||
		data.name.length > 100 ||
		typeof data.serverUrl !== 'string' ||
		data.serverUrl.length > 2048 ||
		typeof data.clientId !== 'string' ||
		!/^[a-zA-Z0-9_-]{1,128}$/.test(data.clientId) ||
		!Array.isArray(data.scopes) ||
		!data.scopes.length ||
		!data.scopes.every(
			(scope: unknown) =>
				typeof scope === 'string' && /^[a-z]+:[a-z]+$/.test(scope),
		) ||
		!data.scopes.some(
			(scope) => scope === 'portfolio:read' || scope === 'api:read',
		) ||
		typeof data.allowInsecureHttp !== 'boolean' ||
		Object.keys(data).some(
			(key) =>
				![
					'name',
					'serverUrl',
					'clientId',
					'scopes',
					'allowInsecureHttp',
				].includes(key),
		)
	)
		throw new Error('Invalid connection configuration.');
	let base: URL;
	try {
		base = new URL(data.serverUrl);
	} catch {
		throw new Error('Invalid server URL.');
	}
	if (
		!['http:', 'https:'].includes(base.protocol) ||
		!base.hostname ||
		(base.protocol === 'http:' && !data.allowInsecureHttp) ||
		base.username ||
		base.password ||
		base.search ||
		base.hash
	)
		throw new Error(
			'The server URL requires HTTPS or an explicit HTTP opt-in.',
		);
	base.pathname = base.pathname.replace(/\/+$/, '') + '/';
	const scopes = [...new Set(data.scopes as string[])].sort();
	const serverUrl = base.href.replace(/\/$/, '');
	const storageKey = createHash('sha256')
		.update(JSON.stringify([serverUrl, data.clientId, scopes]))
		.digest('hex');
	return {
		name: data.name.trim(),
		serverUrl,
		clientId: data.clientId,
		scopes,
		storageKey,
		authorizationUrl: new URL('api/oauth/authorize', base).href,
		tokenUrl: new URL('api/oauth/token', base).href,
		revocationUrl: new URL('api/oauth/revoke', base).href,
		userUrl: new URL('api/v1/me', base).href,
	};
}

export const defaultConnection = parseConnection(bundledConnection);
