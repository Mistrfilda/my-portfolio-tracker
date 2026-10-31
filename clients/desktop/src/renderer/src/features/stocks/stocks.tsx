import { useRef, useState } from 'react';
import { ChevronRight, LoaderCircle, RefreshCw, Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { dateTime, money, percent, tone } from '../dashboard/format';
import { DataIssue, Empty } from '../dashboard/section';
import { filterHoldings, stockSortOptions, type StockSort } from './filter';
import { HoldingDetail } from './holding-detail';
import { useStocks } from './use-stocks';

export function Stocks() {
	const { view, loading, refresh } = useStocks();
	const [query, setQuery] = useState('');
	const [currency, setCurrency] = useState('');
	const [sort, setSort] = useState<StockSort>('value-desc');
	const [selectedId, setSelectedId] = useState<string | null>(null);
	const detailRef = useRef<HTMLDivElement>(null);
	const portfolio = view?.data;
	const items = portfolio?.items ?? [];
	const filtered = filterHoldings(items, query, currency, sort);
	const selected =
		filtered.find((item) => item.stock.id === selectedId) ?? filtered[0];
	const currencies = [
		...new Set(items.map((item) => item.stock.currency)),
	].sort();
	const total = portfolio?.total;
	return (
		<div className="mx-auto max-w-7xl space-y-6 pb-4">
			<div className="flex flex-wrap items-center justify-between gap-4">
				<div>
					<p className="mb-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-primary">
						Akciové portfolio
					</p>
					<h1 className="text-3xl font-semibold tracking-tight">
						Akcie a pozice
					</h1>
					<p className="mt-2 text-sm text-muted-foreground">
						Držené akcie, jednotlivé nákupy a jejich výkonnost.
					</p>
				</div>
				<Button
					variant="outline"
					size="sm"
					disabled={loading}
					onClick={refresh}
				>
					<RefreshCw
						aria-hidden="true"
						className={loading ? 'animate-spin' : ''}
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
					{view ? 'Obnovuji akcie a pozice…' : 'Načítám akcie a pozice…'}
				</p>
			)}
			{!view && (
				<div
					aria-hidden="true"
					className="h-72 animate-pulse rounded-xl border bg-card"
				/>
			)}
			{view?.issue && (
				<DataIssue issue={view.issue} stale={portfolio != null} />
			)}
			{total && (
				<>
					<div className="grid gap-4 md:grid-cols-3">
						<Card className="gap-3 border-primary/20 bg-primary/5 p-5 shadow-none">
							<h2 className="text-xs font-medium text-muted-foreground">
								Hodnota akcií · CZK
							</h2>
							<p className="text-2xl font-semibold tabular-nums">
								{money(total.currentValue)}
							</p>
							<p className="text-xs text-muted-foreground">
								{items.length} společností ·{' '}
								{items.reduce(
									(count, item) => count + item.positions.length,
									0,
								)}{' '}
								otevřených pozic
							</p>
						</Card>
						<Card className="gap-3 p-5 shadow-none">
							<h2 className="text-xs font-medium text-muted-foreground">
								Investováno · CZK
							</h2>
							<p className="text-2xl font-semibold tabular-nums">
								{money(total.investedAmount)}
							</p>
							<p className="text-xs text-muted-foreground">
								Historické náklady u brokera
							</p>
						</Card>
						<Card className="gap-3 p-5 shadow-none">
							<h2 className="text-xs font-medium text-muted-foreground">
								Zisk / ztráta · CZK
							</h2>
							<p
								className={
									'text-2xl font-semibold tabular-nums ' + tone(total.profit)
								}
							>
								{money(total.profit)}
							</p>
							<p
								className={
									'text-xs font-medium tabular-nums ' +
									tone(total.profitPercent)
								}
							>
								{percent(total.profitPercent)} · bez dividend
							</p>
						</Card>
					</div>
					{items.length === 0 ? (
						<Empty>Zatím nemáte žádné otevřené akciové pozice.</Empty>
					) : (
						<>
							<Card className="gap-0 overflow-hidden py-0 shadow-none">
								<div className="flex flex-wrap items-center gap-3 border-b p-4">
									<div className="relative min-w-48 flex-1">
										<Search
											className="pointer-events-none absolute left-3 top-2.5 size-4 text-muted-foreground"
											aria-hidden="true"
										/>
										<input
											type="search"
											aria-label="Hledat akcii podle názvu nebo tickeru"
											placeholder="Název nebo ticker…"
											value={query}
											onChange={(event) => setQuery(event.target.value)}
											className="h-9 w-full rounded-md border bg-background pl-9 pr-3 text-sm"
										/>
									</div>
									<select
										aria-label="Obchodní měna"
										value={currency}
										onChange={(event) => setCurrency(event.target.value)}
										className="h-9 rounded-md border bg-background px-3 text-sm"
									>
										<option value="">Všechny měny</option>
										{currencies.map((item) => (
											<option key={item}>{item}</option>
										))}
									</select>
									<select
										aria-label="Řazení akcií"
										value={sort}
										onChange={(event) =>
											setSort(event.target.value as StockSort)
										}
										className="h-9 max-w-full rounded-md border bg-background px-3 text-sm"
									>
										{Object.entries(stockSortOptions).map(([value, label]) => (
											<option key={value} value={value}>
												{label}
											</option>
										))}
									</select>
								</div>
								{filtered.length === 0 ? (
									<div className="space-y-3 p-5">
										<Empty>Zadanému hledání neodpovídá žádná akcie.</Empty>
										<Button
											variant="outline"
											size="sm"
											onClick={() => {
												setQuery('');
												setCurrency('');
											}}
										>
											Vymazat filtry
										</Button>
									</div>
								) : (
									<div className="max-h-[28rem] overflow-auto">
										<table className="w-full min-w-[880px] text-sm">
											<caption className="sr-only">
												Přehled držených akcií
											</caption>
											<thead className="sticky top-0 z-10 bg-muted text-xs text-muted-foreground">
												<tr>
													<th scope="col" className="px-4 py-3 text-left">
														Společnost
													</th>
													<th scope="col" className="px-3 py-3 text-right">
														Cena / kusy
													</th>
													<th scope="col" className="px-3 py-3 text-right">
														Hodnota · CZK
													</th>
													<th scope="col" className="px-3 py-3 text-right">
														Podíl akcií
													</th>
													<th scope="col" className="px-3 py-3 text-right">
														Div. výnos / cena
													</th>
													<th scope="col" className="px-3 py-3 text-right">
														Zisk · měna brokera
													</th>
													<th scope="col" className="px-3 py-3">
														<span className="sr-only">Pozice</span>
													</th>
												</tr>
											</thead>
											<tbody className="divide-y">
												{filtered.map((holding) => (
													<tr
														key={holding.stock.id}
														className={
															holding.stock.id === selected?.stock.id
																? 'bg-primary/5'
																: 'hover:bg-muted/40'
														}
													>
														<th
															scope="row"
															className="px-4 py-3 text-left font-medium"
														>
															<span
																className="block max-w-56 truncate"
																title={holding.stock.name}
															>
																{holding.stock.name}
															</span>
															<span className="mt-1 block text-xs font-normal text-muted-foreground">
																{holding.stock.ticker} ·{' '}
																{holding.stock.exchange}
															</span>
														</th>
														<td className="whitespace-nowrap px-3 py-3 text-right tabular-nums">
															{money(
																holding.stock.currentPrice,
																holding.stock.currency,
																2,
															)}
															<span className="mt-1 block text-xs text-muted-foreground">
																{holding.pieces.toLocaleString('cs-CZ')} ks
															</span>
														</td>
														<td className="whitespace-nowrap px-3 py-3 text-right font-medium tabular-nums">
															{money(holding.currentValueCzk)}
														</td>
														<td className="px-3 py-3 text-right tabular-nums">
															{percent(holding.portfolioSharePercent, false)}
														</td>
														<td className="px-3 py-3 text-right tabular-nums text-primary">
															{percent(
																holding.dividends.yieldOnPricePercent,
																false,
															)}
														</td>
														<td
															className={
																'whitespace-nowrap px-3 py-3 text-right tabular-nums ' +
																tone(holding.brokerAmounts.profit)
															}
														>
															{percent(holding.brokerAmounts.profitPercent)}
															<span className="mt-1 block text-xs">
																{money(
																	holding.brokerAmounts.profit,
																	holding.brokerCurrency,
																	2,
																)}
															</span>
														</td>
														<td className="px-3 py-3">
															<Button
																variant="ghost"
																size="icon"
																aria-label={
																	'Zobrazit pozice ' + holding.stock.name
																}
																aria-pressed={
																	holding.stock.id === selected?.stock.id
																}
																aria-controls="holding-detail"
																onClick={() => {
																	setSelectedId(holding.stock.id);
																	detailRef.current?.scrollIntoView({
																		behavior: 'smooth',
																		block: 'start',
																	});
																}}
															>
																<ChevronRight aria-hidden="true" />
															</Button>
														</td>
													</tr>
												))}
											</tbody>
										</table>
									</div>
								)}
								<p className="border-t px-4 py-3 text-xs text-muted-foreground">
									Zobrazeno {filtered.length} z {items.length} společností.
									Podíly se vztahují k celému akciovému portfoliu.
								</p>
							</Card>
							{selected && (
								<div
									id="holding-detail"
									ref={detailRef}
									className="scroll-mt-6"
								>
									<HoldingDetail holding={selected} />
								</div>
							)}
						</>
					)}
					<p className="text-xs text-muted-foreground">
						Přehled sestaven {dateTime(portfolio.generatedAt)}. Aktuálnost cen
						najdete u jednotlivých akcií.
					</p>
				</>
			)}
		</div>
	);
}
