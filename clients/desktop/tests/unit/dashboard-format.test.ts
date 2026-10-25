import { describe, expect, it } from 'vitest';
import {
	date,
	money,
	percent,
	tone,
} from '../../src/renderer/src/features/dashboard/format';

describe('dashboard presentation units', () => {
	it('formats percentage points without multiplying API returns by 100', () => {
		expect(percent(12.5).replaceAll('\u00a0', ' ')).toBe('+12,5 %');
		expect(percent(-1.25).replaceAll('\u00a0', ' ')).toBe('-1,25 %');
		expect(percent(null)).toBe('—');
		expect(tone(null)).toBe('text-muted-foreground');
	});
	it('preserves a dividend currency and calendar date', () => {
		expect(money(106.25, 'USD', 2)).toContain('106,25');
		expect(money(106.25, 'USD', 2)).toContain('US$');
		expect(date('2026-01-01').replaceAll('\u00a0', ' ')).toBe('1. 1. 2026');
		expect(date(null)).toBe('—');
	});
});
