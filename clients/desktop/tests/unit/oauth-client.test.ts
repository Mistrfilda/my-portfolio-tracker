import { describe, expect, it, vi } from 'vitest';
import { OAuthClient } from '../../src/main/auth/client';
import { defaultConnection } from '../../src/main/auth/config';
import { AuthError } from '../../src/main/auth/errors';

const reply = {
	token_type: 'Bearer',
	access_token: 'test-access-token',
	refresh_token: 'test-refresh-token',
	expires_in: 600,
};
const code = {
	code: 'test-code',
	verifier: 'test-verifier',
	redirectUri: 'http://127.0.0.1:54321/oauth/callback',
};

describe('OAuth HTTP contract', () => {
	it('exchanges a code using form encoding and no client secret, cookies or redirects', async () => {
		const request = vi
			.fn<typeof fetch>()
			.mockResolvedValue(Response.json(reply));
		const client = new OAuthClient(defaultConnection, request, () => 1000);
		expect(await client.exchange(code)).toEqual({
			accessToken: reply.access_token,
			refreshToken: reply.refresh_token,
			expiresAt: 601000,
		});
		const [url, options] = request.mock.calls[0]!;
		expect(url).toBe(defaultConnection.tokenUrl);
		expect(options).toMatchObject({
			method: 'POST',
			credentials: 'omit',
			redirect: 'error',
			cache: 'no-store',
		});
		expect(options?.headers).toMatchObject({
			'Content-Type': 'application/x-www-form-urlencoded',
		});
		expect(
			Object.fromEntries(new URLSearchParams(options?.body as string)),
		).toEqual({
			client_id: defaultConnection.clientId,
			grant_type: 'authorization_code',
			code: code.code,
			code_verifier: code.verifier,
			redirect_uri: code.redirectUri,
		});
	});
	it('rotates and revokes the refresh token using the public client ID', async () => {
		const request = vi
			.fn<typeof fetch>()
			.mockResolvedValueOnce(Response.json(reply))
			.mockResolvedValueOnce(new Response(null, { status: 200 }));
		const client = new OAuthClient(defaultConnection, request);
		await client.refresh('test-old-refresh');
		await client.revoke('test-new-refresh');
		expect(
			Object.fromEntries(
				new URLSearchParams(request.mock.calls[0]![1]?.body as string),
			),
		).toEqual({
			client_id: defaultConnection.clientId,
			grant_type: 'refresh_token',
			refresh_token: 'test-old-refresh',
		});
		expect(request.mock.calls[1]![0]).toBe(defaultConnection.revocationUrl);
		expect(
			Object.fromEntries(
				new URLSearchParams(request.mock.calls[1]![1]?.body as string),
			),
		).toEqual({
			client_id: defaultConnection.clientId,
			token: 'test-new-refresh',
			token_type_hint: 'refresh_token',
		});
	});
	it('only returns the declared user fields', async () => {
		const request = vi.fn<typeof fetch>().mockResolvedValue(
			Response.json({
				id: 'test-id',
				name: 'Test User',
				username: 'test',
				unexpected: 'private',
			}),
		);
		expect(
			await new OAuthClient(defaultConnection, request).user('test-access'),
		).toEqual({ id: 'test-id', name: 'Test User', username: 'test' });
		expect(request.mock.calls[0]![1]?.headers).toMatchObject({
			Authorization: 'Bearer test-access',
		});
	});
	it.each([
		{ ...reply, refresh_token: undefined },
		{ ...reply, token_type: 'Other' },
		{ ...reply, expires_in: -1 },
		{ ...reply, expires_in: '600' },
		{ ...reply, access_token: 'header\r\ninjection' },
	])('rejects malformed token responses', async (value) => {
		await expect(
			new OAuthClient(defaultConnection, async () =>
				Response.json(value),
			).exchange(code),
		).rejects.toEqual(new AuthError('invalid-response'));
	});
	it('does not expose server errors or request details', async () => {
		await expect(
			new OAuthClient(defaultConnection, async () =>
				Response.json(
					{
						error: 'invalid_grant',
						error_description: 'private diagnostic data',
					},
					{ status: 400 },
				),
			).refresh('test-refresh'),
		).rejects.toEqual(new AuthError('expired'));
		await expect(
			new OAuthClient(defaultConnection, async () => {
				throw new Error('private connection information');
			}).user('test-access'),
		).rejects.toEqual(new AuthError('network'));
	});
	it('rejects non-JSON and oversized responses', async () => {
		await expect(
			new OAuthClient(
				defaultConnection,
				async () => new Response('<html>login</html>'),
			).exchange(code),
		).rejects.toEqual(new AuthError('invalid-response'));
		await expect(
			new OAuthClient(defaultConnection, async () =>
				Response.json({ text: 'x'.repeat(300000) }),
			).exchange(code),
		).rejects.toEqual(new AuthError('invalid-response'));
	});
});
