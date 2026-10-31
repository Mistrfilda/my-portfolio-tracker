import type { Currency, PortfolioAmounts } from '../../shared/dashboard';

export function invalid(): never {
	throw new Error('Invalid API response.');
}

export function object(value: unknown): Record<string, unknown> {
	if (typeof value !== 'object' || value === null || Array.isArray(value))
		return invalid();
	return value as Record<string, unknown>;
}

export function number(value: unknown): number {
	return typeof value === 'number' && Number.isFinite(value)
		? value
		: invalid();
}

export function string(value: unknown): string {
	return typeof value === 'string' && value.length > 0 && value.length <= 512
		? value
		: invalid();
}

export function currency(value: unknown): Currency {
	return ['CZK', 'USD', 'EUR', 'GBP', 'PLN', 'NOK'].includes(string(value))
		? (value as Currency)
		: invalid();
}

export function nullable<T>(
	value: unknown,
	parse: (value: unknown) => T,
): T | null {
	return value === null ? null : parse(value);
}

export function list<T>(
	value: unknown,
	parse: (value: unknown) => T,
	max: number,
): T[] {
	if (!Array.isArray(value) || value.length > max) return invalid();
	return value.map(parse);
}

export function date(value: unknown): string {
	const text = string(value);
	if (!/^\d{4}-\d{2}-\d{2}$/.test(text)) return invalid();
	const parsed = new Date(text + 'T00:00:00Z');
	if (
		!Number.isFinite(parsed.getTime()) ||
		parsed.toISOString().slice(0, 10) !== text
	)
		return invalid();
	return text;
}

export function timestamp(value: unknown): string {
	const text = string(value);
	if (
		!/^\d{4}-\d{2}-\d{2}T.*(?:Z|[+-]\d{2}:\d{2})$/.test(text) ||
		!Number.isFinite(Date.parse(text))
	)
		return invalid();
	return text;
}

export function amounts(value: unknown): PortfolioAmounts {
	const data = object(value);
	return {
		investedAmount: number(data.investedAmount),
		currentValue: number(data.currentValue),
		profit: number(data.profit),
		profitPercent: number(data.profitPercent),
	};
}
