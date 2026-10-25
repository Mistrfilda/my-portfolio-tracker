import type {
	SessionIssue,
	SessionState,
	SessionUser,
} from '../../shared/session';
import type { AuthorizationCode, OAuthClient, Tokens } from './client';
import type { Connection } from './config';
import { AuthError, sessionIssue } from './errors';
import type { TokenStore } from './token-store';

type Client = Pick<OAuthClient, 'exchange' | 'refresh' | 'user' | 'revoke'>;

export class SessionService {
	private tokens: Tokens | null = null;
	private state: SessionState;
	private queue: Promise<void> = Promise.resolve();
	private loginAbort?: AbortController;
	private loginTask?: Promise<void>;
	private timer?: ReturnType<typeof setTimeout>;
	private disposed = false;
	private requestGeneration = 0;

	constructor(
		connection: Connection,
		private readonly client: Client,
		private readonly store: TokenStore,
		private readonly authorize: (
			signal: AbortSignal,
		) => Promise<AuthorizationCode>,
		private readonly changed: (state: SessionState) => void,
		private readonly now: () => number = Date.now,
	) {
		this.state = {
			revision: 0,
			status: 'restoring',
			user: null,
			issue: null,
			connection: { name: connection.name, serverUrl: connection.serverUrl },
		};
	}

	getState(): SessionState {
		return structuredClone(this.state);
	}

	private update(
		status: SessionState['status'],
		user: SessionUser | null = null,
		issue: SessionIssue | null = null,
	): void {
		this.state = {
			...this.state,
			revision: this.state.revision + 1,
			status,
			user,
			issue,
		};
		if (!this.disposed) this.changed(this.getState());
	}

	private run(work: () => Promise<void>): Promise<void> {
		const result = this.queue.then(async () => {
			if (this.disposed) return;
			try {
				await work();
			} catch (error) {
				this.stopTimer();
				const issue = sessionIssue(error);
				this.update(
					this.tokens || issue === 'storage' ? 'unavailable' : 'signed-out',
					null,
					issue,
				);
			}
		});
		this.queue = result;
		return result;
	}

	initialize(): Promise<void> {
		return this.run(async () => {
			this.tokens = await this.store.read();
			await this.loadUser();
		});
	}

	signIn(): Promise<void> {
		if (this.loginTask) return this.loginTask;
		if (this.state.status !== 'signed-out') return Promise.resolve();
		const controller = new AbortController();
		this.loginAbort = controller;
		this.update('signing-in');
		this.loginTask = this.run(async () => {
			const code = await this.authorize(controller.signal);
			if (controller.signal.aborted) throw new AuthError('cancelled');
			const tokens = await this.client.exchange(code);
			if (controller.signal.aborted || this.disposed) {
				await this.revokeQuietly(tokens);
				throw new AuthError('cancelled');
			}
			try {
				await this.store.save(tokens);
			} catch (error) {
				await this.revokeQuietly(tokens);
				throw error;
			}
			this.tokens = tokens;
			try {
				await this.loadUser(false, controller.signal);
			} finally {
				if (controller.signal.aborted && this.tokens) {
					const cancelled = this.tokens;
					await this.store.clear();
					this.tokens = null;
					await this.revokeQuietly(cancelled);
					this.stopTimer();
					this.update('signed-out');
				}
			}
		}).finally(() => {
			this.loginTask = undefined;
			this.loginAbort = undefined;
		});
		return this.loginTask;
	}

	cancelSignIn(): void {
		this.loginAbort?.abort();
	}

	retry(): Promise<void> {
		if (!['signed-in', 'unavailable'].includes(this.state.status))
			return Promise.resolve();
		return this.run(async () => {
			this.update('restoring');
			if (!this.tokens) this.tokens = await this.store.read();
			await this.loadUser();
		});
	}

	signOut(): Promise<void> {
		this.cancelSignIn();
		this.requestGeneration++;
		const couldNotRead = this.state.issue === 'storage' && !this.tokens;
		this.update('signing-out');
		return this.run(async () => {
			this.stopTimer();
			const previous = this.tokens;
			// If deleting fails, keep the session recoverable and report the failure.
			await this.store.clear();
			this.tokens = null;
			const revoked = previous
				? await this.revokeQuietly(previous)
				: !couldNotRead;
			this.update('signed-out', null, revoked ? null : 'logout-incomplete');
		});
	}

	// Main-only operation: serialize authenticated reads with refresh and logout.
	// The callback and token must never cross the preload boundary.
	readAuthenticated<T>(read: (accessToken: string) => Promise<T>): Promise<T> {
		const generation = this.requestGeneration;
		const owner = this.state.user?.id;
		const valid = () => !this.disposed && generation === this.requestGeneration;
		const result = this.queue.then(async () => {
			if (
				!valid() ||
				!owner ||
				this.state.status !== 'signed-in' ||
				this.state.user?.id !== owner ||
				!this.tokens
			)
				throw new AuthError('expired');
			let refreshed = false;
			try {
				if (this.tokens.expiresAt <= this.now() + 30_000) {
					await this.refresh();
					refreshed = true;
				}
				if (!valid()) throw new AuthError('cancelled');
				let data: T;
				try {
					data = await read(this.tokens!.accessToken);
				} catch (error) {
					if (
						!(error instanceof AuthError) ||
						error.issue !== 'expired' ||
						refreshed ||
						!valid()
					)
						throw error;
					await this.refresh();
					if (!valid()) throw new AuthError('cancelled');
					data = await read(this.tokens!.accessToken);
				}
				if (!valid()) throw new AuthError('cancelled');
				this.scheduleRefresh();
				return data;
			} catch (error) {
				if (valid() && error instanceof AuthError) {
					this.stopTimer();
					try {
						if (error.issue === 'expired') {
							const previous = this.tokens;
							await this.store.clear();
							this.tokens = null;
							if (previous) await this.revokeQuietly(previous);
						}
					} catch {
						this.update('unavailable', null, 'storage');
						throw new AuthError('storage');
					}
					this.update(
						this.tokens || error.issue === 'storage'
							? 'unavailable'
							: 'signed-out',
						null,
						sessionIssue(error),
					);
				}
				throw error;
			}
		});
		this.queue = result.then(
			() => undefined,
			() => undefined,
		);
		return result;
	}

	private async refresh(): Promise<void> {
		if (!this.tokens) throw new AuthError('expired');
		const previous = this.tokens;
		// Never replay a rotating refresh token after a crash or uncertain response.
		await this.store.clear();
		this.tokens = null;
		let tokens: Tokens;
		try {
			tokens = await this.client.refresh(previous.refreshToken);
		} catch {
			throw new AuthError('expired');
		}
		try {
			await this.store.save(tokens);
		} catch (error) {
			await this.revokeQuietly(tokens);
			throw error;
		}
		this.tokens = tokens;
	}

	private async loadUser(
		forceRefresh = false,
		signal?: AbortSignal,
	): Promise<void> {
		if (this.state.status === 'signing-out') return;
		const generation = this.requestGeneration;
		if (!this.tokens) {
			this.update('signed-out');
			return;
		}
		let refreshed = false;
		if (forceRefresh || this.tokens.expiresAt <= this.now() + 30_000) {
			await this.refresh();
			refreshed = true;
		}
		try {
			let user: SessionUser;
			try {
				user = await this.client.user(this.tokens!.accessToken);
			} catch (error) {
				if (
					!(error instanceof AuthError) ||
					error.issue !== 'expired' ||
					refreshed
				)
					throw error;
				await this.refresh();
				user = await this.client.user(this.tokens!.accessToken);
			}
			if (signal?.aborted) throw new AuthError('cancelled');
			if (generation !== this.requestGeneration) return;
			this.update('signed-in', user);
			this.scheduleRefresh();
		} catch (error) {
			if (error instanceof AuthError && error.issue === 'expired') {
				const revoked = this.tokens;
				await this.store.clear();
				this.tokens = null;
				if (revoked) await this.revokeQuietly(revoked);
			}
			throw error;
		}
	}

	private async revokeQuietly(tokens: Tokens): Promise<boolean> {
		try {
			await this.client.revoke(tokens.refreshToken);
			return true;
		} catch {
			return false;
		}
	}

	private scheduleRefresh(): void {
		this.stopTimer();
		if (!this.tokens || this.disposed) return;
		const expectedAccessToken = this.tokens.accessToken;
		this.timer = setTimeout(
			() => {
				void this.run(async () => {
					if (this.tokens?.accessToken === expectedAccessToken)
						await this.loadUser(true);
				});
			},
			Math.max(1000, this.tokens.expiresAt - this.now() - 30_000),
		);
		this.timer.unref();
	}

	private stopTimer(): void {
		if (this.timer) clearTimeout(this.timer);
		this.timer = undefined;
	}

	dispose(): void {
		this.disposed = true;
		this.requestGeneration++;
		this.cancelSignIn();
		this.stopTimer();
	}
}
