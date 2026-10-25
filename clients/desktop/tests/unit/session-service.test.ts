import { afterEach, describe, expect, it, vi } from 'vitest';
import type { SessionState } from '../../src/shared/session';
import type { AuthorizationCode, Tokens } from '../../src/main/auth/client';
import { defaultConnection } from '../../src/main/auth/config';
import { AuthError } from '../../src/main/auth/errors';
import { SessionService } from '../../src/main/auth/session-service';

const user = { id: 'test-user', name: 'Test User', username: 'test' };
const code: AuthorizationCode = {
	code: 'test-code',
	verifier: 'test-verifier',
	redirectUri: 'http://127.0.0.1:54321/oauth/callback',
};
const services: SessionService[] = [];

function deferred<T>() {
	let resolve!: (value: T) => void;
	const promise = new Promise<T>((done) => {
		resolve = done;
	});
	return { promise, resolve };
}

function fixture(saved: Tokens | null = null) {
	let persisted = saved;
	const tokens = (): Tokens => ({
		accessToken: 'test-access-new',
		refreshToken: 'test-refresh-new',
		expiresAt: Date.now() + 600_000,
	});
	const client = {
		exchange: vi.fn<(code: AuthorizationCode) => Promise<Tokens>>(async () =>
			tokens(),
		),
		refresh: vi.fn<(refresh: string) => Promise<Tokens>>(async () => {
			expect(persisted).toBeNull();
			return tokens();
		}),
		user: vi.fn<(access: string) => Promise<typeof user>>(async () => user),
		revoke: vi.fn<(refresh: string) => Promise<void>>(async () => undefined),
	};
	const store = {
		read: vi.fn(async () => persisted),
		save: vi.fn(async (value: Tokens) => {
			persisted = value;
		}),
		clear: vi.fn(async () => {
			persisted = null;
		}),
	};
	const authorize = vi.fn<(signal: AbortSignal) => Promise<AuthorizationCode>>(
		async () => code,
	);
	const states: SessionState[] = [];
	const service = new SessionService(
		defaultConnection,
		client,
		store,
		authorize,
		(state) => states.push(state),
	);
	services.push(service);
	return {
		service,
		client,
		store,
		authorize,
		states,
		persisted: () => persisted,
	};
}

describe('authenticated dashboard reads', () => {
	it('rejects reads without a signed-in user', async () => {
		const f = fixture();
		await f.service.initialize();
		const read = vi.fn(async () => 'private-data');
		await expect(f.service.readAuthenticated(read)).rejects.toMatchObject({
			issue: 'expired',
		});
		expect(read).not.toHaveBeenCalled();
	});
	it('refreshes an expired token only once across concurrent reads', async () => {
		vi.useFakeTimers();
		const f = fixture();
		await f.service.initialize();
		await f.service.signIn();
		vi.setSystemTime(Date.now() + 600_000);
		const read = vi.fn(async () => 'private-data');
		expect(
			await Promise.all([
				f.service.readAuthenticated(read),
				f.service.readAuthenticated(read),
			]),
		).toEqual(['private-data', 'private-data']);
		expect(f.client.refresh).toHaveBeenCalledTimes(1);
	});
	it('retries a rejected read once and clears a session rejected again', async () => {
		const f = fixture();
		await f.service.initialize();
		await f.service.signIn();
		const read = vi.fn(async () => {
			throw new AuthError('expired');
		});
		await expect(f.service.readAuthenticated(read)).rejects.toMatchObject({
			issue: 'expired',
		});
		expect(read).toHaveBeenCalledTimes(2);
		expect(f.client.refresh).toHaveBeenCalledTimes(1);
		expect(f.persisted()).toBeNull();
		expect(f.service.getState().status).toBe('signed-out');
	});
	it('recovers a single 401 and keeps resource failures separate from login', async () => {
		const f = fixture();
		await f.service.initialize();
		await f.service.signIn();
		const read = vi
			.fn(async () => 'private-data')
			.mockRejectedValueOnce(new AuthError('expired'));
		expect(await f.service.readAuthenticated(read)).toBe('private-data');
		expect(f.service.getState().status).toBe('signed-in');
		await expect(
			f.service.readAuthenticated(async () => {
				throw new Error('resource failure');
			}),
		).rejects.toThrow('resource failure');
		expect(f.service.getState().status).toBe('signed-in');
	});
	it('never delivers an in-flight portfolio after logout is requested', async () => {
		const f = fixture();
		await f.service.initialize();
		await f.service.signIn();
		const pending = deferred<string>();
		const read = vi.fn(() => pending.promise);
		const result = f.service.readAuthenticated(read);
		await vi.waitFor(() => expect(read).toHaveBeenCalledTimes(1));
		const rejected = expect(result).rejects.toMatchObject({
			issue: 'cancelled',
		});
		const logout = f.service.signOut();
		expect(f.service.getState().status).toBe('signing-out');
		pending.resolve('private-data');
		await rejected;
		await logout;
		expect(f.service.getState().status).toBe('signed-out');
	});
	it('does not replay a refresh after an uncertain failure during data loading', async () => {
		vi.useFakeTimers();
		const f = fixture();
		await f.service.initialize();
		await f.service.signIn();
		vi.setSystemTime(Date.now() + 600_000);
		f.client.refresh.mockRejectedValue(new AuthError('network'));
		const read = vi.fn(async () => 'private-data');
		await expect(f.service.readAuthenticated(read)).rejects.toMatchObject({
			issue: 'expired',
		});
		await expect(f.service.readAuthenticated(read)).rejects.toMatchObject({
			issue: 'expired',
		});
		expect(read).not.toHaveBeenCalled();
		expect(f.client.refresh).toHaveBeenCalledTimes(1);
		expect(f.persisted()).toBeNull();
	});
});

afterEach(() => {
	for (const service of services.splice(0)) service.dispose();
	vi.useRealTimers();
});

describe('native session lifecycle', () => {
	it('persists a user session and never includes tokens in renderer state', async () => {
		const f = fixture();
		await f.service.initialize();
		expect(f.service.getState().status).toBe('signed-out');
		await f.service.signIn();
		expect(f.service.getState()).toMatchObject({
			status: 'signed-in',
			user,
			issue: null,
		});
		expect(f.persisted()?.refreshToken).toBe('test-refresh-new');
		expect(JSON.stringify(f.states)).not.toContain('test-access');
		expect(JSON.stringify(f.states)).not.toContain('test-refresh');
		expect(f.states.map((state) => state.revision)).toEqual([1, 2, 3]);
	});
	it('restores and rotates expired credentials after removing the old stored token', async () => {
		const f = fixture({
			accessToken: 'test-old-access',
			refreshToken: 'test-old-refresh',
			expiresAt: Date.now() - 1,
		});
		await f.service.initialize();
		expect(f.client.refresh).toHaveBeenCalledExactlyOnceWith(
			'test-old-refresh',
		);
		expect(f.persisted()?.refreshToken).toBe('test-refresh-new');
		expect(f.service.getState().status).toBe('signed-in');
	});
	it('serializes concurrent requests that need a refresh', async () => {
		vi.useFakeTimers();
		const f = fixture({
			accessToken: 'test-old-access',
			refreshToken: 'test-old-refresh',
			expiresAt: Date.now() + 600_000,
		});
		await f.service.initialize();
		vi.setSystemTime(Date.now() + 600_000);
		await Promise.all([
			f.service.retry(),
			f.service.retry(),
			f.service.retry(),
		]);
		expect(f.client.refresh).toHaveBeenCalledTimes(1);
		expect(f.service.getState().status).toBe('signed-in');
	});
	it('refreshes shortly before expiry without user interaction', async () => {
		vi.useFakeTimers();
		const f = fixture({
			accessToken: 'test-old-access',
			refreshToken: 'test-old-refresh',
			expiresAt: Date.now() + 60_000,
		});
		await f.service.initialize();
		await vi.advanceTimersByTimeAsync(30_000);
		expect(f.client.refresh).toHaveBeenCalledTimes(1);
		expect(f.service.getState().status).toBe('signed-in');
	});
	it('refreshes at most once on a 401 and invalidates a still-rejected session', async () => {
		const f = fixture({
			accessToken: 'test-old-access',
			refreshToken: 'test-old-refresh',
			expiresAt: Date.now() + 600_000,
		});
		f.client.user.mockRejectedValue(new AuthError('expired'));
		await f.service.initialize();
		expect(f.client.refresh).toHaveBeenCalledTimes(1);
		expect(f.client.user).toHaveBeenCalledTimes(2);
		expect(f.persisted()).toBeNull();
		expect(f.service.getState()).toMatchObject({
			status: 'signed-out',
			issue: 'expired',
		});
	});
	it('never replays an old refresh token after an uncertain network failure or restart', async () => {
		const f = fixture({
			accessToken: 'test-old-access',
			refreshToken: 'test-old-refresh',
			expiresAt: Date.now() - 1,
		});
		f.client.refresh.mockRejectedValue(new AuthError('network'));
		await f.service.initialize();
		expect(f.persisted()).toBeNull();
		expect(f.service.getState()).toMatchObject({
			status: 'signed-out',
			issue: 'expired',
		});
		await f.service.initialize();
		expect(f.client.refresh).toHaveBeenCalledTimes(1);
	});
	it('retains an unexpired session when user lookup is offline and can retry', async () => {
		const saved = {
			accessToken: 'test-old-access',
			refreshToken: 'test-old-refresh',
			expiresAt: Date.now() + 600_000,
		};
		const f = fixture(saved);
		f.client.user.mockRejectedValueOnce(new AuthError('network'));
		await f.service.initialize();
		expect(f.service.getState()).toMatchObject({
			status: 'unavailable',
			issue: 'network',
		});
		expect(f.persisted()).toEqual(saved);
		await f.service.retry();
		expect(f.service.getState().status).toBe('signed-in');
		expect(f.client.refresh).not.toHaveBeenCalled();
	});
	it('revokes newly issued credentials if secure storage fails', async () => {
		const f = fixture();
		f.store.save.mockRejectedValue(new AuthError('storage'));
		await f.service.initialize();
		await f.service.signIn();
		expect(f.client.revoke).toHaveBeenCalledExactlyOnceWith('test-refresh-new');
		expect(f.persisted()).toBeNull();
		expect(f.service.getState()).toMatchObject({
			status: 'unavailable',
			issue: 'storage',
		});
	});
	it('allows only one browser flow and cancels it without issuing tokens', async () => {
		const f = fixture();
		f.authorize.mockImplementation(
			(signal) =>
				new Promise((_resolve, reject) => {
					if (signal.aborted) reject(new AuthError('cancelled'));
					signal.addEventListener(
						'abort',
						() => reject(new AuthError('cancelled')),
						{ once: true },
					);
				}),
		);
		await f.service.initialize();
		const first = f.service.signIn();
		expect(f.service.signIn()).toBe(first);
		await Promise.resolve();
		f.service.cancelSignIn();
		await first;
		expect(f.authorize).toHaveBeenCalledTimes(1);
		expect(f.client.exchange).not.toHaveBeenCalled();
		expect(f.service.getState()).toMatchObject({
			status: 'signed-out',
			issue: null,
		});
	});
	it('revokes a token received after cancellation instead of saving it', async () => {
		const f = fixture();
		const pending = deferred<Tokens>();
		f.client.exchange.mockReturnValue(pending.promise);
		await f.service.initialize();
		const login = f.service.signIn();
		await vi.waitFor(() => expect(f.client.exchange).toHaveBeenCalledTimes(1));
		f.service.cancelSignIn();
		pending.resolve({
			accessToken: 'test-late-access',
			refreshToken: 'test-late-refresh',
			expiresAt: Date.now() + 600_000,
		});
		await login;
		expect(f.store.save).not.toHaveBeenCalled();
		expect(f.client.revoke).toHaveBeenCalledWith('test-late-refresh');
		expect(f.service.getState().status).toBe('signed-out');
	});
	it('clears a stored session when cancellation arrives during user lookup', async () => {
		const f = fixture();
		const pending = deferred<typeof user>();
		f.client.user.mockReturnValue(pending.promise);
		await f.service.initialize();
		const login = f.service.signIn();
		await vi.waitFor(() => expect(f.client.user).toHaveBeenCalledTimes(1));
		f.service.cancelSignIn();
		pending.resolve(user);
		await login;
		expect(f.states.some((state) => state.status === 'signed-in')).toBe(false);
		expect(f.persisted()).toBeNull();
		expect(f.client.revoke).toHaveBeenCalledTimes(1);
		expect(f.service.getState().status).toBe('signed-out');
	});
	it('removes local credentials on logout even when server revocation is offline', async () => {
		const f = fixture();
		await f.service.initialize();
		await f.service.signIn();
		f.client.revoke.mockRejectedValue(new AuthError('network'));
		await f.service.signOut();
		expect(f.persisted()).toBeNull();
		expect(f.service.getState()).toMatchObject({
			status: 'signed-out',
			user: null,
			issue: 'logout-incomplete',
		});
	});
	it('does not pretend logout succeeded when credentials cannot be deleted', async () => {
		const f = fixture();
		await f.service.initialize();
		await f.service.signIn();
		f.store.clear.mockRejectedValue(new AuthError('storage'));
		await f.service.signOut();
		expect(f.persisted()).not.toBeNull();
		expect(f.service.getState()).toMatchObject({
			status: 'unavailable',
			issue: 'storage',
		});
	});
});
