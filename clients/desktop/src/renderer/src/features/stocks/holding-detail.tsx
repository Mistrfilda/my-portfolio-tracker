import type { StockHolding } from '../../../../shared/stocks';
import { Badge } from '@/components/ui/badge';
import { date, dateTime, money, percent, tone } from '../dashboard/format';
import { Panel } from '../dashboard/section';

export function HoldingDetail({ holding }: { holding: StockHolding }) {
	const { stock, amounts, brokerAmounts, dividends } = holding;
	const metrics = [
		{
			label: 'Počet akcií',
			value: holding.pieces.toLocaleString('cs-CZ') + ' ks',
		},
		{
			label: 'Průměrná nákupní cena',
			value: money(holding.averagePurchasePrice, stock.currency, 2),
		},
		{
			label: 'Investováno · ' + stock.currency,
			value: money(amounts.investedAmount, stock.currency, 2),
		},
		{
			label: 'Aktuální hodnota · ' + stock.currency,
			value: money(amounts.currentValue, stock.currency, 2),
		},
	];
	return (
		<Panel
			title={'Pozice · ' + stock.name}
			description={`${stock.ticker} · ${stock.exchange} · ${holding.positions.length} otevřených pozic`}
		>
			<div className="space-y-6">
				<div className="flex flex-wrap items-center justify-between gap-3">
					<Badge variant="secondary" className="text-primary">
						Cena akcie: {money(stock.currentPrice, stock.currency, 2)}
					</Badge>
					<p className="text-xs text-muted-foreground">
						{stock.priceUpdatedAt
							? 'Cena aktualizována ' + dateTime(stock.priceUpdatedAt)
							: 'Cena zatím nebyla aktualizována'}
					</p>
				</div>
				<dl className="grid grid-cols-2 gap-4 xl:grid-cols-4">
					{metrics.map((metric) => (
						<div key={metric.label} className="rounded-lg bg-muted/50 p-3">
							<dt className="text-xs text-muted-foreground">{metric.label}</dt>
							<dd className="mt-2 text-lg font-semibold tabular-nums">
								{metric.value}
							</dd>
						</div>
					))}
				</dl>
				<div className="overflow-x-auto rounded-lg border">
					<table className="w-full min-w-[680px] text-sm">
						<caption className="sr-only">
							Jednotlivé nákupy {stock.name}
						</caption>
						<thead className="bg-muted/50 text-xs text-muted-foreground">
							<tr>
								<th scope="col" className="px-4 py-3 text-left">
									Datum nákupu
								</th>
								<th scope="col" className="px-4 py-3 text-right">
									Kusy
								</th>
								<th scope="col" className="px-4 py-3 text-right">
									Cena za kus
								</th>
								<th scope="col" className="px-4 py-3 text-right">
									Investováno u brokera
								</th>
								<th scope="col" className="px-4 py-3 text-right">
									Hodnota / zisk · {stock.currency}
								</th>
							</tr>
						</thead>
						<tbody className="divide-y">
							{holding.positions.map((position) => (
								<tr key={position.id}>
									<td className="whitespace-nowrap px-4 py-3">
										{date(position.orderDate)}
									</td>
									<td className="px-4 py-3 text-right tabular-nums">
										{position.pieces.toLocaleString('cs-CZ')}
									</td>
									<td className="whitespace-nowrap px-4 py-3 text-right tabular-nums">
										{money(position.purchasePrice, stock.currency, 2)}
									</td>
									<td className="whitespace-nowrap px-4 py-3 text-right tabular-nums">
										{money(
											position.brokerCost.amount,
											position.brokerCost.currency,
											2,
										)}
									</td>
									<td className="whitespace-nowrap px-4 py-3 text-right tabular-nums">
										<p className="font-medium">
											{money(position.amounts.currentValue, stock.currency, 2)}
										</p>
										<p
											className={
												'mt-1 text-xs ' + tone(position.amounts.profit)
											}
										>
											{money(position.amounts.profit, stock.currency, 2)} ·{' '}
											{percent(position.amounts.profitPercent)}
										</p>
									</td>
								</tr>
							))}
						</tbody>
					</table>
				</div>
				<div className="grid gap-4 xl:grid-cols-2">
					<div className="rounded-lg border p-4">
						<h3 className="text-sm font-medium">
							Zisk / ztráta otevřených pozic
						</h3>
						<dl className="mt-4 space-y-3 text-sm">
							<div className="flex flex-wrap justify-between gap-2">
								<dt className="text-muted-foreground">
									Obchodní měna · {stock.currency}
								</dt>
								<dd
									className={'font-medium tabular-nums ' + tone(amounts.profit)}
								>
									{money(amounts.profit, stock.currency, 2)} ·{' '}
									{percent(amounts.profitPercent)}
								</dd>
							</div>
							<div className="flex flex-wrap justify-between gap-2">
								<dt className="text-muted-foreground">
									Měna brokera · {holding.brokerCurrency}
								</dt>
								<dd
									className={
										'font-medium tabular-nums ' + tone(brokerAmounts.profit)
									}
								>
									{money(brokerAmounts.profit, holding.brokerCurrency, 2)} ·{' '}
									{percent(brokerAmounts.profitPercent)}
								</dd>
							</div>
							<div className="flex flex-wrap justify-between gap-2 border-t pt-3">
								<dt className="text-muted-foreground">Hodnota v CZK</dt>
								<dd className="font-medium tabular-nums">
									{money(holding.currentValueCzk)}
								</dd>
							</div>
						</dl>
					</div>
					<div className="rounded-lg border border-primary/20 bg-primary/5 p-4">
						<h3 className="text-sm font-medium">
							Dividendový výnos před zdaněním
						</h3>
						<dl className="mt-4 space-y-3 text-sm">
							<div className="flex justify-between gap-2">
								<dt className="text-muted-foreground">K aktuální ceně</dt>
								<dd className="font-semibold tabular-nums text-primary">
									{percent(dividends.yieldOnPricePercent, false)}
								</dd>
							</div>
							<div className="flex justify-between gap-2">
								<dt className="text-muted-foreground">
									K průměrné nákupní ceně
								</dt>
								<dd className="font-semibold tabular-nums text-primary">
									{percent(dividends.yieldOnCostPercent, false)}
								</dd>
							</div>
							<div className="flex justify-between gap-2 border-t pt-3">
								<dt className="text-muted-foreground">Dividenda na akcii</dt>
								<dd className="font-medium tabular-nums">
									{money(dividends.grossPerShare, stock.currency, 2)}
								</dd>
							</div>
						</dl>
						<p className="mt-4 text-xs leading-5 text-muted-foreground">
							Události s ex-datem od {date(dividends.fromDate)}, včetně
							oznámených budoucích dividend. Výnos není předpověď.
						</p>
					</div>
				</div>
			</div>
		</Panel>
	);
}
