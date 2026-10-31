import type { StockPortfolioResult } from '../../shared/stocks';
import type { SessionService } from '../auth/session-service';
import { ApiClient } from '../api/client';
import { parseStockPortfolio } from './contracts';

export class StocksClient extends ApiClient {
	load(token: string): Promise<StockPortfolioResult> {
		return this.get(
			'portfolio/stocks',
			token,
			parseStockPortfolio,
			2 * 1024 * 1024,
		);
	}
}

export class StocksService {
	private pending?: Promise<StockPortfolioResult>;

	constructor(
		private readonly auth: Pick<
			SessionService,
			'readAuthenticated' | 'getState'
		>,
		private readonly client: StocksClient,
	) {}

	get(): Promise<StockPortfolioResult> {
		if (this.auth.getState().status !== 'signed-in')
			return Promise.resolve({ data: null, issue: 'session' });
		if (!this.pending) {
			this.pending = this.auth
				.readAuthenticated((token) => this.client.load(token))
				.catch((): StockPortfolioResult => ({ data: null, issue: 'session' }))
				.finally(() => {
					this.pending = undefined;
				});
		}
		return this.pending;
	}
}
