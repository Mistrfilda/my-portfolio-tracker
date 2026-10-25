import { ArrowDownRight, ArrowUpRight, Minus } from 'lucide-react';
import type {
	DashboardSummary,
	ExchangeRate,
	StockMover,
} from '../../../../shared/dashboard';
import { date, percent, tone } from './format';
import { DataIssue, Empty, Panel } from './section';
import type { DashboardSection } from './use-dashboard';

function Movers({ title, items }: { title: string; items: StockMover[] }) {
	return (
		<div>
			<h3 className="mb-2 text-xs font-medium text-muted-foreground">
				{title}
			</h3>
			<ul className="divide-y">
				{items.map(({ stock, changePercent }) => {
					const Icon =
						changePercent > 0
							? ArrowUpRight
							: changePercent < 0
								? ArrowDownRight
								: Minus;
					return (
						<li
							key={stock.id}
							className="flex items-center justify-between gap-3 py-3"
						>
							<div className="min-w-0">
								<p className="text-sm font-semibold">{stock.ticker}</p>
								<p
									className="mt-1 truncate text-xs text-muted-foreground"
									title={stock.name}
								>
									{stock.name}
								</p>
							</div>
							<span
								className={
									'flex shrink-0 items-center gap-1 text-sm font-medium tabular-nums ' +
									tone(changePercent)
								}
							>
								<Icon className="size-3.5" aria-hidden="true" />
								{percent(changePercent)}
							</span>
						</li>
					);
				})}
			</ul>
		</div>
	);
}

export function StockMovers({
	movers,
}: {
	movers: DashboardSummary['stockMovers'];
}) {
	return (
		<Panel
			title="Denní pohyby akcií"
			description="Vaše otevřené akcie vůči předchozímu obchodnímu dni"
		>
			{!movers.best.length && !movers.worst.length ? (
				<Empty>V portfoliu zatím nejsou otevřené akciové pozice.</Empty>
			) : (
				<div className="grid gap-6 sm:grid-cols-2">
					<Movers title="Nejlepší změna" items={movers.best} />
					<Movers title="Nejhorší změna" items={movers.worst} />
				</div>
			)}
			<p className="mt-4 text-xs leading-5 text-muted-foreground">
				Při malém počtu akcií se mohou seznamy překrývat. Při chybějící cenové
				historii se zobrazuje 0 %.
			</p>
		</Panel>
	);
}

export function ExchangeRates({
	section,
}: {
	section: DashboardSection<ExchangeRate[]>;
}) {
	return (
		<Panel
			title="Měnové kurzy"
			description="Poslední uložené kurzy · hodnota za 1 jednotku výchozí měny"
		>
			{section.issue && (
				<div className="mb-4">
					<DataIssue issue={section.issue} stale={section.data !== null} />
				</div>
			)}
			{section.data && (
				<div className="grid gap-x-6 gap-y-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
					{section.data.map((item) => (
						<div
							key={item.fromCurrency + item.toCurrency}
							className="border-l-2 pl-3"
						>
							<p className="text-xs text-muted-foreground">
								{item.fromCurrency} / {item.toCurrency}
							</p>
							<p className="mt-1 font-semibold tabular-nums">
								{new Intl.NumberFormat('cs-CZ', {
									maximumFractionDigits: 4,
								}).format(item.rate)}{' '}
								<span className="text-xs font-normal text-muted-foreground">
									{item.toCurrency}
								</span>
							</p>
							<p className="mt-1 text-[11px] text-muted-foreground">
								{item.source === 'CNB' ? 'ČNB' : 'ECB'} · {date(item.forDate)}
							</p>
						</div>
					))}
				</div>
			)}
		</Panel>
	);
}
