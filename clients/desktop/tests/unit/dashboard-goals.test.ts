import { describe, expect, it } from 'vitest';
import { goalPeriod } from '../../src/renderer/src/features/dashboard/goal-period';
import { goals } from '../fixtures/dashboard';

describe('goal periods', () => {
	it('groups a full calendar year separately from monthly recurring goals', () => {
		expect(goalPeriod(goals[0]!)).toBe('yearly');
		expect(goalPeriod(goals[2]!)).toBe('monthly');
	});
	it.each([
		['2024-02-01', '2024-02-29', 'monthly'],
		['2026-02-01', '2026-02-28', 'monthly'],
		['2026-01-01', '2026-01-31', 'monthly'],
		['2026-02-01', '2026-02-27', 'other'],
		['2026-01-01', '2027-12-31', 'other'],
		['2026-10-01', '2027-09-30', 'other'],
		['2026-10-26', '2026-11-01', 'other'],
	])(
		'classifies %s to %s as %s without inventing an annual goal',
		(startDate, endDate, period) => {
			expect(goalPeriod({ ...goals[0]!, startDate, endDate })).toBe(period);
		},
	);
	it('keeps weekly recurrence out of monthly and annual groups', () => {
		expect(goalPeriod({ ...goals[2]!, repeatable: 'weekly' })).toBe('other');
	});
});
