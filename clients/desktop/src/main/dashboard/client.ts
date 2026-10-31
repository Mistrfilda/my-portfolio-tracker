import type { DashboardIssue, DashboardSnapshot } from '../../shared/dashboard';
import { ApiClient } from '../api/client';
import type { SessionService } from '../auth/session-service';
import {
	parseDividends,
	parseGoals,
	parseRates,
	parseSummary,
} from './contracts';

export function dashboardFailure(issue: DashboardIssue): DashboardSnapshot {
	return {
		summary: { data: null, issue },
		dividends: { data: null, issue },
		rates: { data: null, issue },
		goals: { data: null, issue },
	};
}

export class DashboardClient extends ApiClient {
	async load(token: string): Promise<DashboardSnapshot> {
		// Settle every read before refreshing so no old-token request remains in flight.
		const results = await Promise.allSettled([
			this.get('dashboard/summary', token, parseSummary),
			this.get('portfolio/dividends?limit=8', token, parseDividends),
			this.get('exchange-rates', token, parseRates),
			this.get('portfolio/goals', token, parseGoals),
		]);
		const [summary, dividends, rates, goals] = results;
		for (const result of results)
			if (result.status === 'rejected') throw result.reason;
		if (
			summary.status !== 'fulfilled' ||
			dividends.status !== 'fulfilled' ||
			rates.status !== 'fulfilled' ||
			goals.status !== 'fulfilled'
		)
			throw new Error('Dashboard request did not complete.');
		return {
			summary: summary.value,
			dividends: dividends.value,
			rates: rates.value,
			goals: goals.value,
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
