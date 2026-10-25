import type {
	DashboardIssue,
	DashboardResult,
	DashboardSnapshot,
} from '../../shared/dashboard';
import { readJson } from '../auth/client';
import type { Connection } from '../auth/config';
import { AuthError } from '../auth/errors';
import type { SessionService } from '../auth/session-service';
import { parseDividends, parseRates, parseSummary } from './contracts';

export function dashboardFailure(issue: DashboardIssue): DashboardSnapshot {
	return {
		summary: { data: null, issue },
		dividends: { data: null, issue },
		rates: { data: null, issue },
	};
}

export class DashboardClient {
	constructor(
		private readonly connection: Connection,
		private readonly request: typeof fetch = fetch,
	) {}

	private async get<T>(
		path: string,
		token: string,
		parse: (value: unknown) => T,
	): Promise<DashboardResult<T>> {
		let response: Response;
		try {
			response = await this.request(
				new URL('api/v1/' + path, this.connection.serverUrl + '/'),
				{
					method: 'GET',
					headers: {
						Accept: 'application/json',
						Authorization: 'Bearer ' + token,
					},
					redirect: 'error',
					credentials: 'omit',
					cache: 'no-store',
					signal: AbortSignal.timeout(15_000),
				},
			);
		} catch {
			return { data: null, issue: 'network' };
		}
		if (!response.ok) {
			await response.body?.cancel().catch(() => undefined);
			if (response.status === 401) throw new AuthError('expired');
			return {
				data: null,
				issue:
					response.status === 403
						? 'permissions'
						: response.status === 503
							? 'data-unavailable'
							: 'server',
			};
		}
		try {
			return { data: parse(await readJson(response)), issue: null };
		} catch {
			return { data: null, issue: 'invalid-response' };
		}
	}

	async load(token: string): Promise<DashboardSnapshot> {
		// Settle every read before refreshing so no old-token request remains in flight.
		const results = await Promise.allSettled([
			this.get('dashboard/summary', token, parseSummary),
			this.get('portfolio/dividends?limit=8', token, parseDividends),
			this.get('exchange-rates', token, parseRates),
		]);
		const [summary, dividends, rates] = results;
		for (const result of results)
			if (result.status === 'rejected') throw result.reason;
		if (
			summary.status !== 'fulfilled' ||
			dividends.status !== 'fulfilled' ||
			rates.status !== 'fulfilled'
		)
			throw new Error('Dashboard request did not complete.');
		return {
			summary: summary.value,
			dividends: dividends.value,
			rates: rates.value,
		};
	}
}

export class DashboardService {
	private pending?: Promise<DashboardSnapshot>;

	constructor(
		private readonly auth: Pick<
			SessionService,
			'readAuthenticated' | 'getState'
		>,
		private readonly client: DashboardClient,
	) {}

	get(): Promise<DashboardSnapshot> {
		if (this.auth.getState().status !== 'signed-in')
			return Promise.resolve(dashboardFailure('session'));
		if (!this.pending) {
			this.pending = this.auth
				.readAuthenticated((token) => this.client.load(token))
				.catch(() => dashboardFailure('session'))
				.finally(() => {
					this.pending = undefined;
				});
		}
		return this.pending;
	}
}
