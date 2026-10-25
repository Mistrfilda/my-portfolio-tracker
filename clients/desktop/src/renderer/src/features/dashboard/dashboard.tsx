import { LoaderCircle, RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { DividendSummary, RecentDividends } from './dividends';
import { ExchangeRates, StockMovers } from './market';
import { PortfolioSummary } from './portfolio-summary';
import { DataIssue } from './section';
import { useDashboard } from './use-dashboard';

export function Dashboard() {
	const { view, loading, refresh } = useDashboard();
	const summary = view?.summary.data;
	return (
		<div className="mx-auto max-w-7xl space-y-6 pb-4">
			<div className="flex flex-wrap items-center justify-between gap-4">
				<div>
					<p className="mb-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-primary">
						Vaše investice
					</p>
					<h1 className="text-3xl font-semibold tracking-tight">
						Přehled portfolia
					</h1>
					<p className="mt-2 text-sm text-muted-foreground">
						Hodnota, výkonnost a příjmy na jednom místě.
					</p>
				</div>
				<Button
					variant="outline"
					size="sm"
					disabled={loading}
					onClick={refresh}
				>
					<RefreshCw
						className={loading ? 'animate-spin' : ''}
						aria-hidden="true"
					/>
					{loading ? 'Načítání…' : 'Obnovit'}
				</Button>
			</div>
			{loading && (
				<p
					role="status"
					className="flex items-center gap-2 text-sm text-muted-foreground"
				>
					<LoaderCircle className="size-4 animate-spin" aria-hidden="true" />
					{view ? 'Obnovuji data portfolia…' : 'Načítám vaše portfolio…'}
				</p>
			)}
			{!view && (
				<div aria-hidden="true" className="grid gap-4 xl:grid-cols-2">
					{[0, 1, 2, 3].map((key) => (
						<div
							key={key}
							className="h-52 animate-pulse rounded-xl border bg-card"
						/>
					))}
				</div>
			)}
			{view?.summary.issue && (
				<DataIssue issue={view.summary.issue} stale={summary !== null} />
			)}
			{summary && <PortfolioSummary summary={summary} />}
			{summary && (
				<div className="grid items-start gap-4 xl:grid-cols-[0.85fr_1.15fr]">
					<DividendSummary dividends={summary.dividends} />
					<StockMovers movers={summary.stockMovers} />
				</div>
			)}
			{view && (
				<>
					<RecentDividends section={view.dividends} />
					<ExchangeRates section={view.rates} />
				</>
			)}
		</div>
	);
}
