import { createHash, randomBytes, timingSafeEqual } from 'node:crypto';
import { createServer } from 'node:http';
import type { AddressInfo } from 'node:net';
import type { Connection } from './config';
import type { AuthorizationCode } from './client';
import { AuthError } from './errors';

export const CALLBACK_PATH = '/oauth/callback';

export async function authorize(
	connection: Connection,
	openBrowser: (url: string) => Promise<void>,
	signal: AbortSignal,
	timeout = 5 * 60_000,
): Promise<AuthorizationCode> {
	const verifier = randomBytes(32).toString('base64url');
	const state = randomBytes(32).toString('base64url');
	const challenge = createHash('sha256').update(verifier).digest('base64url');
	let redirectUri = '';
	let settled = false;
	let finish!: (value: string | AuthError) => void;
	const result = new Promise<string>((resolve, reject) => {
		finish = (value) => {
			if (settled) return;
			settled = true;
			if (value instanceof AuthError) reject(value);
			else resolve(value);
		};
	});
	// The browser may return or be cancelled before openExternal resolves.
	void result.catch(() => undefined);
	const server = createServer({ maxHeaderSize: 8192 }, (request, response) => {
		response.setHeader('Cache-Control', 'no-store');
		response.setHeader('Referrer-Policy', 'no-referrer');
		response.setHeader(
			'Content-Security-Policy',
			"default-src 'none'; frame-ancestors 'none'",
		);
		response.setHeader('Content-Type', 'text/plain; charset=utf-8');
		response.setHeader('Connection', 'close');
		try {
			const url = new URL(request.url ?? '/', redirectUri);
			const returnedState = url.searchParams.get('state') ?? '';
			if (
				settled ||
				request.method !== 'GET' ||
				request.headers.host !== new URL(redirectUri).host ||
				url.origin !== new URL(redirectUri).origin ||
				url.pathname !== CALLBACK_PATH ||
				url.searchParams.getAll('state').length !== 1 ||
				Buffer.byteLength(returnedState) !== Buffer.byteLength(state) ||
				!timingSafeEqual(Buffer.from(returnedState), Buffer.from(state))
			) {
				response.writeHead(400).end('Invalid authorization callback.');
				return;
			}
			const codes = url.searchParams.getAll('code');
			const errors = url.searchParams.getAll('error');
			if (
				codes.length + errors.length !== 1 ||
				(codes.length === 1 && (!codes[0] || codes[0].length > 8192))
			) {
				response.writeHead(400).end('Invalid authorization callback.');
				return;
			}
			response.end(
				'Požadavek byl předán aplikaci My Portfolio. Můžete se vrátit do aplikace a zavřít tuto kartu.',
				() =>
					finish(
						codes[0] ??
							new AuthError(
								errors[0] === 'access_denied' ? 'denied' : 'server',
							),
					),
			);
		} catch {
			response.writeHead(400).end('Invalid authorization callback.');
		}
	});
	server.requestTimeout = 10_000;
	server.headersTimeout = 10_000;
	const cancel = () => finish(new AuthError('cancelled'));
	const timer = setTimeout(() => finish(new AuthError('timeout')), timeout);
	signal.addEventListener('abort', cancel, { once: true });
	server.on('error', () => finish(new AuthError('browser')));
	try {
		if (signal.aborted) throw new AuthError('cancelled');
		await new Promise<void>((resolve, reject) => {
			server.once('error', reject);
			server.listen(0, '127.0.0.1', () => {
				server.removeListener('error', reject);
				resolve();
			});
		});
		redirectUri =
			'http://127.0.0.1:' +
			(server.address() as AddressInfo).port +
			CALLBACK_PATH;
		if (signal.aborted) throw new AuthError('cancelled');
		const url = new URL(connection.authorizationUrl);
		url.search = new URLSearchParams({
			response_type: 'code',
			client_id: connection.clientId,
			redirect_uri: redirectUri,
			scope: connection.scopes.join(' '),
			state,
			code_challenge: challenge,
			code_challenge_method: 'S256',
		}).toString();
		const browser = Promise.resolve()
			.then(() => openBrowser(url.href))
			.catch(() => {
				throw new AuthError('browser');
			});
		await Promise.race([browser, result.then(() => undefined)]);
		return { code: await result, verifier, redirectUri };
	} finally {
		clearTimeout(timer);
		signal.removeEventListener('abort', cancel);
		server.close();
		server.closeAllConnections();
	}
}
