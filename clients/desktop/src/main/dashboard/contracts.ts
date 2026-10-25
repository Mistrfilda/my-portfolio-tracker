import type {
	Currency,
	DashboardSummary,
	DividendYear,
	ExchangeRate,
	PortfolioAmounts,
	PortfolioDividend,
	StockMover,
	StockReference,
} from '../../shared/dashboard';

function invalid(): never {
	throw new Error('Invalid dashboard response.');
}

function object(value: unknown): Record<string, unknown> {
	if (typeof value !== 'object' || value === null || Array.isArray(value))
		return invalid();
	return value as Record<string, unknown>;
}

function number(value: unknown): number {
	return typeof value === 'number' && Number.isFinite(value)
		? value
		: invalid();
}

function string(value: unknown): string {
	return typeof value === 'string' && value.length > 0 && value.length <= 512
		? value
		: invalid();
}

function currency(value: unknown): Currency {
	return ['CZK', 'USD', 'EUR', 'GBP', 'PLN', 'NOK'].includes(string(value))
		? (value as Currency)
		: invalid();
}

function nullable<T>(value: unknown, parse: (value: unknown) => T): T | null {
	return value === null ? null : parse(value);
}

function list<T>(
	value: unknown,
	parse: (value: unknown) => T,
	max: number,
): T[] {
	if (!Array.isArray(value) || value.length > max) return invalid();
	return value.map(parse);
}

function date(value: unknown): string {
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

function timestamp(value: unknown): string {
	const text = string(value);
	if (
		!/^\d{4}-\d{2}-\d{2}T.*(?:Z|[+-]\d{2}:\d{2})$/.test(text) ||
		!Number.isFinite(Date.parse(text))
	)
		return invalid();
	return text;
}

function amounts(value: unknown): PortfolioAmounts {
	const data = object(value);
	return {
		investedAmount: number(data.investedAmount),
		currentValue: number(data.currentValue),
		profit: number(data.profit),
		profitPercent: number(data.profitPercent),
	};
}

function dividendYear(value: unknown): DividendYear {
	const data = object(value);
	const year = number(data.year);
	if (!Number.isInteger(year) || year < 1000 || year > 9999) return invalid();
	return {
		year,
		grossAmount: number(data.grossAmount),
		netAmount: number(data.netAmount),
	};
}

function stock(value: unknown): StockReference {
	const data = object(value);
	return {
		id: string(data.id),
		name: string(data.name),
		ticker: string(data.ticker),
	};
}

function mover(value: unknown): StockMover {
	const data = object(value);
	return {
		stock: stock(data.stock),
		changePercent: number(data.changePercent),
	};
}

export function parseSummary(value: unknown): DashboardSummary {
	const data = object(value);
	if (data.currency !== 'CZK') return invalid();
	const stocks = object(data.stocks);
	const dividends = object(data.dividends);
	const movers = object(data.stockMovers);
	return {
		generatedAt: timestamp(data.generatedAt),
		currency: 'CZK',
		total: amounts(data.total),
		stocks: {
			amounts: amounts(stocks.amounts),
			closedPositionsProfitIncludingDividends: number(
				stocks.closedPositionsProfitIncludingDividends,
			),
			byTradingCurrency: list(
				stocks.byTradingCurrency,
				(value) => {
					const item = object(value);
					return {
						currency: currency(item.currency),
						currentValue: number(item.currentValue),
					};
				},
				6,
			),
		},
		crypto: amounts(data.crypto),
		portu: amounts(data.portu),
		dividends: {
			byYear: list(dividends.byYear, dividendYear, 1000),
			remainingForecast: nullable(dividends.remainingForecast, dividendYear),
		},
		performance: nullable(data.performance, (value) => {
			const item = object(value);
			return {
				from: nullable(item.from, date),
				to: nullable(item.to, date),
				timeWeightedReturnPercent: number(item.timeWeightedReturnPercent),
				annualizedTimeWeightedReturnPercent: nullable(
					item.annualizedTimeWeightedReturnPercent,
					number,
				),
				moneyWeightedReturnPercent: nullable(
					item.moneyWeightedReturnPercent,
					number,
				),
				xirrPercent: nullable(item.xirrPercent, number),
			};
		}),
		stockMovers: {
			best: list(movers.best, mover, 4),
			worst: list(movers.worst, mover, 4),
		},
	};
}

export function parseDividends(value: unknown): PortfolioDividend[] {
	return list(
		object(value).items,
		(value) => {
			const data = object(value);
			const dividend = object(data.dividend);
			if (typeof data.reinvested !== 'boolean') return invalid();
			return {
				id: string(data.id),
				stock: stock(dividend.stock),
				exDate: date(dividend.exDate),
				paymentDate: nullable(dividend.paymentDate, date),
				currency: currency(data.currency),
				grossAmount: number(data.grossAmount),
				netAmount: number(data.netAmount),
				reinvested: data.reinvested,
			};
		},
		8,
	);
}

export function parseRates(value: unknown): ExchangeRate[] {
	const rates = list<ExchangeRate>(
		object(value).items,
		(value) => {
			const data = object(value);
			const rate = number(data.rate);
			if (rate <= 0 || (data.source !== 'CNB' && data.source !== 'ECB'))
				return invalid();
			return {
				fromCurrency: currency(data.fromCurrency),
				toCurrency: currency(data.toCurrency),
				rate,
				forDate: date(data.forDate),
				updatedAt: timestamp(data.updatedAt),
				source: data.source,
			};
		},
		12,
	);
	if (
		rates.length !== 12 ||
		new Set(rates.map((rate) => rate.fromCurrency + rate.toCurrency)).size !==
			12
	)
		return invalid();
	return rates;
}
