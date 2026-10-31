import { useEffect, useState } from 'react';
import type {
	StockPortfolio,
	StockPortfolioResult,
} from '../../../../shared/stocks';
import type { DashboardSection } from '../dashboard/use-dashboard';

export function useStocks() {
	const [request, setRequest] = useState(0);
	const [completed, setCompleted] = useState<{
		request: number;
		view: DashboardSection<StockPortfolio>;
	} | null>(null);
	useEffect(() => {
		let active = true;
		void window
			.desktop!.stocks.get()
			.catch((): StockPortfolioResult => ({ data: null, issue: 'network' }))
			.then((next) => {
				if (active)
					setCompleted((previous) => ({
						request,
						view: {
							data: next.data ?? previous?.view.data ?? null,
							issue: next.issue,
						},
					}));
			});
		return () => {
			active = false;
		};
	}, [request]);
	return {
		view: completed?.view,
		loading: completed?.request !== request,
		refresh: () => setRequest((value) => value + 1),
	};
}
