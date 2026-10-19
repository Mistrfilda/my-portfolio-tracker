import { describe, expect, it } from 'vitest';
import { parseConnection } from '../../src/main/auth/config';

const input = {
	name: 'Test server',
	serverUrl: 'https://portfolio.example/prefix',
	clientId: 'test-client',
	scopes: ['portfolio:read', 'market:read'],
	allowInsecureHttp: false,
};

describe('desktop connection configuration', () => {
	it('allows HTTP only when the connection explicitly opts in', () => {
		expect(
			parseConnection({
				...input,
				serverUrl: 'http://portfolio.internal',
				allowInsecureHttp: true,
			}).tokenUrl,
		).toBe('http://portfolio.internal/api/oauth/token');
	});
	it('preserves a deployment prefix for OAuth and API endpoints', () => {
		expect(parseConnection(input)).toMatchObject({
			authorizationUrl: 'https://portfolio.example/prefix/api/oauth/authorize',
			userUrl: 'https://portfolio.example/prefix/api/v1/me',
		});
	});
	it.each([
		{ serverUrl: 'http://portfolio.example' },
		{ serverUrl: 'file:///tmp/app' },
		{ serverUrl: 'https://user:password@portfolio.example' },
		{ serverUrl: 'https://portfolio.example/?token=example' },
		{ serverUrl: 'https://portfolio.example/#fragment' },
		{ scopes: ['market:read'] },
		{ scopes: ['portfolio:read evil'] },
		{ clientSecret: 'unexpected-field' },
	])('rejects invalid or ambiguous configuration: %j', (changes) => {
		expect(() => parseConnection({ ...input, ...changes })).toThrow();
	});
	it('separates stored sessions by server, client and scopes', () => {
		const original = parseConnection(input).storageKey;
		expect(
			parseConnection({ ...input, clientId: 'other-client' }).storageKey,
		).not.toBe(original);
		expect(
			parseConnection({ ...input, serverUrl: 'https://other.example' })
				.storageKey,
		).not.toBe(original);
		expect(
			parseConnection({ ...input, scopes: ['portfolio:read'] }).storageKey,
		).not.toBe(original);
		expect(
			parseConnection({ ...input, scopes: [...input.scopes].reverse() })
				.storageKey,
		).toBe(original);
	});
});
