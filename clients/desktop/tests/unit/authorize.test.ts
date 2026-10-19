import { createHash } from 'node:crypto';
import { describe, expect, it, vi } from 'vitest';
import { authorize } from '../../src/main/auth/authorize';
import { defaultConnection } from '../../src/main/auth/config';
import { AuthError } from '../../src/main/auth/errors';

function callback(url: URL, params: Record<string, string>) {
	const target = new URL(url.searchParams.get('redirect_uri')!);
	target.search = new URLSearchParams({
		state: url.searchParams.get('state')!,
		...params,
	}).toString();
	return target;
}

describe('system browser authorization and loopback callback', () => {
	it('uses a random S256 verifier and accepts a valid code on an ephemeral loopback port', async () => {
		let opened!: URL;
		const result = await authorize(
			defaultConnection,
			async (value) => {
				opened = new URL(value);
				const target = callback(opened, { code: 'test-authorization-code' });
				expect(target.hostname).toBe('127.0.0.1');
				expect(target.pathname).toBe('/oauth/callback');
				expect(Number(target.port)).toBeGreaterThan(0);
				const response = await fetch(target);
				expect(response.status).toBe(200);
				expect(response.headers.get('cache-control')).toBe('no-store');
				expect(await response.text()).not.toContain('test-authorization-code');
			},
			new AbortController().signal,
		);
		expect(opened.origin).toBe(defaultConnection.serverUrl);
		expect(opened.searchParams.get('client_id')).toBe(
			defaultConnection.clientId,
		);
		expect(opened.searchParams.get('code_challenge_method')).toBe('S256');
		expect(opened.searchParams.get('scope')?.split(' ').sort()).toEqual(
			defaultConnection.scopes,
		);
		expect(opened.searchParams.has('client_secret')).toBe(false);
		expect(opened.searchParams.has('code_verifier')).toBe(false);
		expect(result.verifier).toHaveLength(43);
		expect(opened.searchParams.get('code_challenge')).toBe(
			createHash('sha256').update(result.verifier).digest('base64url'),
		);
		expect(result.code).toBe('test-authorization-code');
		await expect(fetch(result.redirectUri)).rejects.toThrow();
	});
	it('ignores wrong state, duplicate parameters and wrong paths without consuming the login', async () => {
		await authorize(
			defaultConnection,
			async (value) => {
				const url = new URL(value);
				const wrongState = callback(url, { code: 'test-code', state: 'wrong' });
				expect((await fetch(wrongState)).status).toBe(400);
				const duplicate = callback(url, { code: 'test-code' });
				duplicate.searchParams.append('code', 'another-code');
				expect((await fetch(duplicate)).status).toBe(400);
				const wrongPath = callback(url, { code: 'test-code' });
				wrongPath.pathname = '/wrong';
				expect((await fetch(wrongPath)).status).toBe(400);
				expect((await fetch(callback(url, { code: 'test-code' }))).status).toBe(
					200,
				);
			},
			new AbortController().signal,
		);
	});
	it('handles denial without exchanging a code', async () => {
		await expect(
			authorize(
				defaultConnection,
				async (value) => {
					await fetch(callback(new URL(value), { error: 'access_denied' }));
				},
				new AbortController().signal,
			),
		).rejects.toMatchObject({ issue: 'denied' });
	});
	it('cancels and closes the listener even while the browser launch is unresolved', async () => {
		const controller = new AbortController();
		let redirect = '';
		const result = authorize(
			defaultConnection,
			async (value) => {
				redirect = new URL(value).searchParams.get('redirect_uri')!;
				controller.abort();
				await new Promise(() => undefined);
			},
			controller.signal,
		);
		await expect(result).rejects.toMatchObject({ issue: 'cancelled' });
		await expect(fetch(redirect)).rejects.toThrow();
	});
	it('times out an unfinished login', async () => {
		await expect(
			authorize(
				defaultConnection,
				async () => undefined,
				new AbortController().signal,
				20,
			),
		).rejects.toMatchObject({ issue: 'timeout' });
	});
	it('does not open a browser for a cancelled request', async () => {
		const controller = new AbortController();
		controller.abort();
		const open = vi.fn();
		await expect(
			authorize(defaultConnection, open, controller.signal),
		).rejects.toEqual(new AuthError('cancelled'));
		expect(open).not.toHaveBeenCalled();
	});
	it('sanitizes browser launch errors', async () => {
		await expect(
			authorize(
				defaultConnection,
				async () => {
					throw new Error('private browser detail');
				},
				new AbortController().signal,
			),
		).rejects.toEqual(new AuthError('browser'));
	});
});
