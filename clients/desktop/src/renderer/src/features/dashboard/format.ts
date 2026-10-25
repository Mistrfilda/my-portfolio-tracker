import type { Currency } from '../../../../shared/dashboard';

export function money(
	value: number,
	currency: Currency = 'CZK',
	decimals = 0,
): string {
	return new Intl.NumberFormat('cs-CZ', {
		style: 'currency',
		currency,
		minimumFractionDigits: decimals,
		maximumFractionDigits: decimals,
	}).format(value);
}

export function percent(value: number | null, signed = true): string {
	if (value === null) return '—';
	return new Intl.NumberFormat('cs-CZ', {
		style: 'percent',
		maximumFractionDigits: 2,
		signDisplay: signed ? 'exceptZero' : 'auto',
	}).format(value / 100);
}

export function date(value: string | null): string {
	if (value === null) return '—';
	return new Intl.DateTimeFormat('cs-CZ', {
		day: 'numeric',
		month: 'numeric',
		year: 'numeric',
		timeZone: 'UTC',
	}).format(new Date(value + 'T00:00:00Z'));
}

export function dateTime(value: string): string {
	return new Intl.DateTimeFormat('cs-CZ', {
		dateStyle: 'short',
		timeStyle: 'short',
	}).format(new Date(value));
}

export function tone(value: number | null): string {
	return value === null || value === 0
		? 'text-muted-foreground'
		: value > 0
			? 'text-positive'
			: 'text-negative';
}
