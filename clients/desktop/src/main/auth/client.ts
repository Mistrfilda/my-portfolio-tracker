import type { SessionUser } from '../../shared/session';
import type { Connection } from './config';
import { AuthError } from './errors';

export interface Tokens {
	accessToken: string;
	refreshToken: string;
	expiresAt: number;
}

export interface AuthorizationCode {
	code: string;
	verifier: string;
	redirectUri: string;
}

function isToken(value: unknown): value is string {
	return typeof value === 'string' && /^[\x21-\x7e]{1,65536}$/.test(value);
}

export function parseStoredTokens(value: unknown): Tokens {
	if (typeof value !== 'object' || value === null)
		throw new AuthError('storage');
	const data = value as Record<string, unknown>;
	if (
		!isToken(data.accessToken) ||
		!isToken(data.refreshToken) ||
		typeof data.expiresAt !== 'number' ||
		!Number.isSafeInteger(data.expiresAt) ||
		data.expiresAt <= 0
	) {
		throw new AuthError('storage');
	}
	return {
		accessToken: data.accessToken,
		refreshToken: data.refreshToken,
		expiresAt: data.expiresAt,
	};
}

async function readJson(response: Response): Promise<Record<string, unknown>> {
	if (
		!response.headers.get('content-type')?.includes('application/json') ||
		!response.body
	) {
		throw new AuthError('invalid-response');
	}
	const reader = response.body.getReader();
	const chunks: Uint8Array[] = [];
	let size = 0;
	try {
		for (;;) {
			const { done, value } = await reader.read();
			if (done) break;
			size += value.length;
			if (size > 256 * 1024) throw new AuthError('invalid-response');
			chunks.push(value);
		}
		const data: unknown = JSON.parse(Buffer.concat(chunks).toString('utf8'));
		if (typeof data !== 'object' || data === null || Array.isArray(data))
			throw new AuthError('invalid-response');
		return data as Record<string, unknown>;
	} catch (error) {
		if (error instanceof AuthError) throw error;
		throw new AuthError('invalid-response');
	} finally {
		await reader.cancel().catch(() => undefined);
		reader.releaseLock();
	}
}

export class OAuthClient {
	constructor(
		private readonly connection: Connection,
		private readonly request: typeof fetch = fetch,
		private readonly now: () => number = Date.now,
	) {}

	private async send(url: string, options: RequestInit): Promise<Response> {
		try {
			return await this.request(url, {
				...options,
				redirect: 'error',
				credentials: 'omit',
				cache: 'no-store',
				signal: AbortSignal.timeout(15_000),
				headers: { Accept: 'application/json', ...options.headers },
			});
		} catch {
			throw new AuthError('network');
		}
	}

	private async token(fields: Record<string, string>): Promise<Tokens> {
		const response = await this.send(this.connection.tokenUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams({
				client_id: this.connection.clientId,
				...fields,
			}).toString(),
		});
		const data = await readJson(response);
		if (!response.ok)
			throw new AuthError(
				data.error === 'invalid_grant' ? 'expired' : 'server',
			);
		if (
			data.token_type !== 'Bearer' ||
			!isToken(data.access_token) ||
			!isToken(data.refresh_token) ||
			typeof data.expires_in !== 'number' ||
			!Number.isSafeInteger(data.expires_in) ||
			data.expires_in <= 0 ||
			data.expires_in > 86_400
		)
			throw new AuthError('invalid-response');
		return {
			accessToken: data.access_token,
			refreshToken: data.refresh_token,
			expiresAt: this.now() + data.expires_in * 1000,
		};
	}

	exchange({
		code,
		verifier,
		redirectUri,
	}: AuthorizationCode): Promise<Tokens> {
		return this.token({
			grant_type: 'authorization_code',
			code,
			code_verifier: verifier,
			redirect_uri: redirectUri,
		});
	}

	refresh(refreshToken: string): Promise<Tokens> {
		return this.token({
			grant_type: 'refresh_token',
			refresh_token: refreshToken,
		});
	}

	async user(accessToken: string): Promise<SessionUser> {
		const response = await this.send(this.connection.userUrl, {
			headers: { Authorization: 'Bearer ' + accessToken },
		});
		if (response.status === 401) throw new AuthError('expired');
		if (response.status === 403) throw new AuthError('permissions');
		if (!response.ok) throw new AuthError('server');
		const data = await readJson(response);
		if (
			!['id', 'name', 'username'].every(
				(key) => typeof data[key] === 'string' && data[key].length <= 512,
			) ||
			!data.id ||
			!data.username
		)
			throw new AuthError('invalid-response');
		return {
			id: data.id as string,
			name: data.name as string,
			username: data.username as string,
		};
	}

	async revoke(refreshToken: string): Promise<void> {
		const response = await this.send(this.connection.revocationUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams({
				client_id: this.connection.clientId,
				token: refreshToken,
				token_type_hint: 'refresh_token',
			}).toString(),
		});
		await response.body?.cancel();
		if (!response.ok) throw new AuthError('server');
	}
}
