import { Check, Target } from 'lucide-react';
import type { PortfolioGoal } from '../../../../shared/dashboard';
import { Badge } from '@/components/ui/badge';
import { date, dateTime, money, percent } from './format';
import { goalPeriod } from './goal-period';
import { DataIssue, Empty, Panel } from './section';
import type { DashboardSection } from './use-dashboard';

const labels: Record<PortfolioGoal['type'], string> = {
	total_invested_amount: 'Investovaná částka',
	total_income: 'Příjem z práce',
	total_dividend_amount: 'Příjem z dividend (po zdanění)',
	monthly_income: 'Měsíční příjem z práce',
};

function GoalCard({ goal }: { goal: PortfolioGoal }) {
	const label = labels[goal.type];
	const completion = percent(goal.completionPercent, false);
	return (
		<article className="space-y-4 rounded-xl border bg-background/50 p-4">
			<div className="flex items-start justify-between gap-3">
				<div>
					<h4 className="text-sm font-medium">{label}</h4>
					<p className="mt-1 text-xs text-muted-foreground">
						{date(goal.startDate)} – {date(goal.endDate)}
						{goal.repeatable === 'weekly' && ' · Týdenní cíl'}
					</p>
				</div>
				{goal.remainingAmount <= 0 ? (
					<Badge variant="secondary" className="shrink-0 text-primary">
						<Check aria-hidden="true" /> Splněno
					</Badge>
				) : (
					<Target className="size-4 shrink-0 text-primary" aria-hidden="true" />
				)}
			</div>
			<div className="flex flex-wrap items-baseline justify-between gap-2">
				<p className="text-2xl font-semibold tracking-tight tabular-nums">
					{money(goal.currentValue, goal.currency)}
				</p>
				<p className="text-xs text-muted-foreground">
					Cíl{' '}
					<span className="font-medium tabular-nums text-foreground">
						{money(goal.targetValue, goal.currency)}
					</span>
				</p>
			</div>
			<div>
				<div className="mb-2 flex flex-wrap justify-between gap-2 text-xs">
					<span className="font-semibold tabular-nums text-primary">
						{completion}
					</span>
					<span className="text-muted-foreground">
						{goal.remainingAmount < 0 ? 'Nad cílem' : 'Do cíle'}{' '}
						{money(Math.abs(goal.remainingAmount), goal.currency)}
					</span>
				</div>
				<div
					role="progressbar"
					aria-label={`${label}: ${date(goal.startDate)} – ${date(goal.endDate)}`}
					aria-valuemin={0}
					aria-valuemax={100}
					aria-valuenow={Math.min(100, Math.max(0, goal.completionPercent))}
					aria-valuetext={completion}
					className="h-2 overflow-hidden rounded-full bg-primary/10"
				>
					<div
						className="h-full rounded-full bg-primary"
						style={{
							width: `${Math.min(100, Math.max(0, goal.completionPercent))}%`,
						}}
					/>
				</div>
			</div>
			<div className="flex flex-wrap justify-between gap-2 border-t pt-3 text-[11px] text-muted-foreground">
				<span>Start: {money(goal.valueAtStart, goal.currency)}</span>
				<span>Aktualizováno {dateTime(goal.updatedAt)}</span>
			</div>
		</article>
	);
}

export function PortfolioGoals({
	section,
}: {
	section: DashboardSection<PortfolioGoal[]>;
}) {
	const groups = [
		{
			key: 'yearly',
			title: 'Roční cíle',
			empty: 'Nemáte žádné aktivní roční cíle.',
		},
		{
			key: 'monthly',
			title: 'Měsíční cíle',
			empty: 'Nemáte žádné aktivní měsíční cíle.',
		},
		{ key: 'other', title: 'Další cíle portfolia', empty: '' },
	] as const;
	return (
		<Panel
			title="Aktuální cíle portfolia"
			description="Vaše cíle a postup od počáteční hodnoty k cílové částce."
		>
			<div className="space-y-5">
				{section.issue && (
					<DataIssue issue={section.issue} stale={section.data !== null} />
				)}
				{section.data?.length === 0 && (
					<Empty>Zatím nemáte žádné aktivní cíle portfolia.</Empty>
				)}
				{section.data !== null && section.data.length > 0 && (
					<div className="grid items-start gap-5 xl:grid-cols-2">
						{groups.map((group) => {
							const goals = section.data!.filter(
								(goal) => goalPeriod(goal) === group.key,
							);
							if (group.key === 'other' && goals.length === 0) return null;
							return (
								<section
									key={group.key}
									aria-labelledby={`goals-${group.key}`}
									className="space-y-3"
								>
									<h3
										id={`goals-${group.key}`}
										className="text-xs font-semibold text-muted-foreground"
									>
										{group.title}
									</h3>
									{goals.length === 0 ? (
										<Empty>{group.empty}</Empty>
									) : (
										goals.map((goal) => <GoalCard key={goal.id} goal={goal} />)
									)}
								</section>
							);
						})}
					</div>
				)}
			</div>
		</Panel>
	);
}
