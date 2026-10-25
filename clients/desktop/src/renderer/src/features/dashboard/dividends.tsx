import type {
	DashboardSummary,
	PortfolioDividend,
} from '../../../../shared/dashboard';
import { Badge } from '@/components/ui/badge';
import { date, money } from './format';
import { DataIssue, Empty, Panel } from './section';
import type { DashboardSection } from './use-dashboard';

export function DividendSummary({
	dividends,
}: {
	dividends: DashboardSummary['dividends'];
}) {
	const years = [...dividends.byYear].sort((a, b) => b.year - a.year);
	const max = Math.max(0, ...years.map((item) => item.netAmount));
	const forecast = dividends.remainingForecast;
	return (
		<Panel
			title="Dividendy podle roku"
			description="Čisté evidované nároky v Kč · podle roku ex-date"
		>
			{years.length ? (
				<div className="max-h-64 space-y-4 overflow-y-auto pr-1">
					{years.map((item) => (
						<div key={item.year}>
							<div className="mb-2 flex justify-between gap-4 text-sm">
								<span className="text-muted-foreground">{item.year}</span>
								<span className="font-medium tabular-nums">
									{money(item.netAmount)}
								</span>
							</div>
							<div
								className="h-1.5 overflow-hidden rounded-full bg-muted"
								aria-hidden="true"
							>
								<div
									className="h-full rounded-full bg-primary/70"
									style={{
										width:
											(max > 0
												? (Math.max(0, item.netAmount) / max) * 100
												: 0) + '%',
									}}
								/>
							</div>
						</div>
					))}
				</div>
			) : (
				<Empty>Zatím nemáte evidované dividendy.</Empty>
			)}
			<div className="mt-6 rounded-lg bg-muted/60 p-4">
				<p className="text-xs text-muted-foreground">
					{forecast
						? 'Zbývající čistý odhad pro rok ' + forecast.year
						: 'Zbývající dividendový odhad'}
				</p>
				<p className="mt-2 text-xl font-semibold tabular-nums">
					{forecast ? money(forecast.netAmount) : '—'}
				</p>
				<p className="mt-2 text-xs leading-5 text-muted-foreground">
					{forecast
						? 'Odhad ze serveru při aktuálních měnových kurzech.'
						: 'Pro aktuální rok není připravený odhad.'}
				</p>
			</div>
			<p className="mt-4 text-xs leading-5 text-muted-foreground">
				Nároky zahrnují i oznámené budoucí dividendy. Nejde o přehled již
				přijatých plateb.
			</p>
		</Panel>
	);
}

export function RecentDividends({
	section,
}: {
	section: DashboardSection<PortfolioDividend[]>;
}) {
	return (
		<Panel
			title="Poslední dividendové záznamy"
			description="Nejvýše 8 nároků podle ex-date · částky po zdanění v původní měně"
		>
			{section.issue && (
				<div className="mb-4">
					<DataIssue issue={section.issue} stale={section.data !== null} />
				</div>
			)}
			{section.data &&
				(section.data.length ? (
					<div className="overflow-x-auto">
						<table className="w-full text-sm">
							<caption className="sr-only">
								Osobní dividendové nároky včetně oznámených budoucích dividend
							</caption>
							<thead className="text-xs text-muted-foreground">
								<tr>
									<th className="pb-3 text-left font-normal">Společnost</th>
									<th className="px-4 pb-3 text-left font-normal">
										Ex-date / výplata
									</th>
									<th className="pb-3 text-right font-normal">Čistá částka</th>
								</tr>
							</thead>
							<tbody className="divide-y">
								{section.data.map((item) => (
									<tr key={item.id}>
										<th
											scope="row"
											className="max-w-48 py-3.5 text-left font-normal"
										>
											<p className="font-semibold">{item.stock.ticker}</p>
											<p
												className="mt-1 truncate text-xs text-muted-foreground"
												title={item.stock.name}
											>
												{item.stock.name}
											</p>
										</th>
										<td className="whitespace-nowrap px-4 py-3.5 tabular-nums">
											<p>{date(item.exDate)}</p>
											<p className="mt-1 text-xs text-muted-foreground">
												{item.paymentDate
													? date(item.paymentDate)
													: 'Výplata neoznámena'}
											</p>
										</td>
										<td className="whitespace-nowrap py-3.5 text-right tabular-nums">
											<p className="font-medium">
												{money(item.netAmount, item.currency, 2)}
											</p>
											{item.reinvested && (
												<Badge
													variant="secondary"
													className="mt-1.5 text-[10px]"
												>
													Reinvestováno
												</Badge>
											)}
										</td>
									</tr>
								))}
							</tbody>
						</table>
					</div>
				) : (
					<Empty>Zatím nemáte žádné dividendové záznamy.</Empty>
				))}
		</Panel>
	);
}
