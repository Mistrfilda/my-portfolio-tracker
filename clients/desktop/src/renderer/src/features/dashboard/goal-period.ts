import type { PortfolioGoal } from '../../../../shared/dashboard';

export function goalPeriod(
	goal: PortfolioGoal,
): 'yearly' | 'monthly' | 'other' {
	if (goal.repeatable === 'monthly') return 'monthly';
	if (goal.repeatable === 'weekly') return 'other';
	const sameYear = goal.startDate.slice(0, 4) === goal.endDate.slice(0, 4);
	if (
		sameYear &&
		goal.startDate.endsWith('-01-01') &&
		goal.endDate.endsWith('-12-31')
	)
		return 'yearly';
	const end = new Date(goal.endDate + 'T00:00:00Z');
	end.setUTCDate(end.getUTCDate() + 1);
	if (
		goal.startDate.slice(0, 7) === goal.endDate.slice(0, 7) &&
		goal.startDate.endsWith('-01') &&
		end.getUTCDate() === 1
	)
		return 'monthly';
	return 'other';
}
