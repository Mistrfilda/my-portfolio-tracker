import { ArrowDownRight, ArrowUpRight, Wallet } from 'lucide-react';
import type { DashboardSummary } from '../../../../shared/dashboard';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { date, dateTime, money, percent, tone } from './format';
import { Empty, Panel } from './section';

export function PortfolioSummary({ summary }: { summary: DashboardSummary }) {
	const { total, performance } = summary;
	const assets = [
		{ name: 'Akcie', amounts: summary.stocks.amounts, color: 'bg-chart-1' },
		{ name: 'Krypto', amounts: summary.crypto, color: 'bg-chart-2' },
		{ name: 'Portu', amounts: summary.portu, color: 'bg-chart-3' },
	];
	const positiveTotal = assets.reduce(
		(sum, item) => sum + Math.max(0, item.amounts.currentValue),
		0,
	);
	const ProfitIcon = total.profit < 0 ? ArrowDownRight : ArrowUpRight;
	const currencies = summary.stocks.byTradingCurrency.filter(
		(item) => item.currentValue !== 0,
	);
	return (
		<>
			<div className="grid gap-4 xl:grid-cols-[1.25fr_1fr]">
				<Card className="relative justify-between gap-8 overflow-hidden border-primary/20 bg-primary/5 p-6 shadow-none">
					<div className="flex items-center justify-between">
						<h2 className="text-sm font-medium text-muted-foreground">
							Hodnota portfolia
						</h2>
						<Wallet className="size-5 text-primary" aria-hidden="true" />
					</div>
					<div>
						<p className="text-4xl font-semibold tracking-tight tabular-nums 2xl:text-5xl">
							{money(total.currentValue)}
						</p>
						<p className="mt-3 text-xs text-muted-foreground">
							Aktuální hodnota otevřených pozic · CZK
						</p>
					</div>
				</Card>
				<Card className="gap-5 p-6 shadow-none">
					<div className="flex flex-wrap items-center justify-between gap-2">
						<h2 className="text-sm font-medium text-muted-foreground">
							Zisk / ztráta otevřených pozic
						</h2>
						<Badge variant="secondary" className={tone(total.profitPercent)}>
							<ProfitIcon aria-hidden="true" />
							{percent(total.profitPercent)}
						</Badge>
					</div>
					<p
						className={
							'text-3xl font-semibold tracking-tight tabular-nums ' +
							tone(total.profit)
						}
					>
						{total.profit > 0 ? '+' : ''}
						{money(total.profit)}
					</p>
					<div className="flex flex-wrap items-center justify-between gap-2 border-t pt-4 text-sm">
						<span className="text-muted-foreground">
							Investováno v otevřených pozicích
						</span>
						<span className="font-medium tabular-nums">
							{money(total.investedAmount)}
						</span>
					</div>
				</Card>
			</div>
			{total.currentValue === 0 && total.investedAmount === 0 && (
				<Empty>
					V portfoliu zatím nejsou žádné otevřené investice. Po jejich přidání
					ve webové aplikaci zde uvidíte přehled.
				</Empty>
			)}
			<div className="grid items-start gap-4 xl:grid-cols-[1.25fr_1fr]">
				<Panel
					title="Složení portfolia"
					description="Aktuální hodnota podle typu investice"
				>
					{positiveTotal > 0 && (
						<div
							className="mb-6 flex h-2.5 gap-1 overflow-hidden rounded-full"
							aria-hidden="true"
						>
							{assets.map((asset) => (
								<div
									key={asset.name}
									className={asset.color + ' rounded-full'}
									style={{
										width:
											(Math.max(0, asset.amounts.currentValue) /
												positiveTotal) *
												100 +
											'%',
									}}
								/>
							))}
						</div>
					)}
					<div className="overflow-x-auto">
						<table className="w-full text-sm">
							<caption className="sr-only">
								Hodnota, podíl a zisk podle typu investice
							</caption>
							<thead className="text-xs text-muted-foreground">
								<tr>
									<th className="pb-3 text-left font-normal">Investice</th>
									<th className="pb-3 text-right font-normal">
										Hodnota / podíl
									</th>
									<th className="pb-3 pl-4 text-right font-normal">
										Zisk / ztráta
									</th>
								</tr>
							</thead>
							<tbody className="divide-y">
								{assets.map(({ name, amounts, color }) => (
									<tr key={name}>
										<th scope="row" className="py-4 pr-3 text-left font-medium">
											<span
												className={
													'mr-2 inline-block size-2 rounded-full ' + color
												}
											/>
											{name}
										</th>
										<td className="py-4 text-right tabular-nums">
											<p className="whitespace-nowrap font-medium">
												{money(amounts.currentValue)}
											</p>
											<p className="mt-1 text-xs text-muted-foreground">
												{total.currentValue > 0
													? percent(
															(amounts.currentValue / total.currentValue) * 100,
															false,
														)
													: '—'}
											</p>
										</td>
										<td
											className={
												'whitespace-nowrap py-4 pl-4 text-right tabular-nums ' +
												tone(amounts.profit)
											}
										>
											<p>{money(amounts.profit)}</p>
											<p className="mt-1 text-xs">
												{percent(amounts.profitPercent)}
											</p>
										</td>
									</tr>
								))}
							</tbody>
						</table>
					</div>
					{currencies.length > 0 && (
						<div className="mt-3 border-t pt-4">
							<p className="mb-3 text-xs text-muted-foreground">
								Akcie podle obchodní měny · hodnoty v Kč
							</p>
							<div className="flex flex-wrap gap-2">
								{currencies.map((item) => (
									<Badge
										key={item.currency}
										variant="outline"
										className="gap-2 py-1.5 font-normal"
									>
										<span className="font-medium">{item.currency}</span>
										{money(item.currentValue)}
									</Badge>
								))}
							</div>
						</div>
					)}
				</Panel>
				<Panel
					title="Výkonnost portfolia"
					description={
						performance
							? 'Uložené statistiky · ' +
								date(performance.from) +
								' – ' +
								date(performance.to)
							: 'Historické zhodnocení vašich investic'
					}
				>
					{performance ? (
						<dl className="grid grid-cols-2 gap-x-6 gap-y-7">
							{[
								{
									label: 'TWR',
									value: performance.timeWeightedReturnPercent,
									detail: 'Bez vlivu vkladů a výběrů',
								},
								{
									label: 'TWR ročně',
									value: performance.annualizedTimeWeightedReturnPercent,
									detail: 'Přepočet TWR na jeden rok',
								},
								{
									label: 'MWR',
									value: performance.moneyWeightedReturnPercent,
									detail: 'Zohledňuje peněžní toky',
								},
								{
									label: 'XIRR',
									value: performance.xirrPercent,
									detail: 'Roční výnos dle načasování toků',
								},
							].map((item) => (
								<div key={item.label}>
									<dt className="text-xs text-muted-foreground">
										{item.label}
									</dt>
									<dd
										className={
											'mt-2 text-2xl font-semibold tabular-nums ' +
											tone(item.value)
										}
									>
										{percent(item.value)}
									</dd>
									<dd className="mt-1.5 text-xs leading-5 text-muted-foreground">
										{item.value === null
											? 'Nedostatek dat pro výpočet'
											: item.detail}
									</dd>
								</div>
							))}
						</dl>
					) : (
						<Empty>
							Pro výpočet výkonnosti zatím není dostatek historických statistik.
						</Empty>
					)}
					<div className="mt-6 border-t pt-4">
						<p className="text-xs leading-5 text-muted-foreground">
							Realizovaný zisk akcií včetně dividend
						</p>
						<p
							className={
								'mt-1 text-lg font-semibold tabular-nums ' +
								tone(summary.stocks.closedPositionsProfitIncludingDividends)
							}
						>
							{money(summary.stocks.closedPositionsProfitIncludingDividends)}
						</p>
					</div>
				</Panel>
			</div>
			<p className="text-xs leading-5 text-muted-foreground">
				Souhrn sestaven {dateTime(summary.generatedAt)}. Jednotlivé ceny mohou
				pocházet z různých okamžiků.
			</p>
		</>
	);
}
