import type {
	DashboardSummary,
	DividendYear,
	ExchangeRate,
	PortfolioDividend,
	PortfolioGoal,
	StockMover,
	StockReference,
} from '../../shared/dashboard';

import {
	amounts,
	currency,
	date,
	invalid,
	list,
	nullable,
	number,
	object,
	string,
	timestamp,
} from '../api/contracts';

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

export function parseGoals(value: unknown): PortfolioGoal[] {
	return list(
		object(value).items,
		(value) => {
			const data = object(value);
			const type = string(data.type);
			if (
				![
					'total_invested_amount',
					'total_income',
					'total_dividend_amount',
					'monthly_income',
				].includes(type) ||
				(data.repeatable !== null &&
					data.repeatable !== 'monthly' &&
					data.repeatable !== 'weekly')
			)
				return invalid();
			const startDate = date(data.startDate);
			const endDate = date(data.endDate);
			if (endDate < startDate) return invalid();
			return {
				id: string(data.id),
				type: type as PortfolioGoal['type'],
				currency: currency(data.currency),
				startDate,
				endDate,
				repeatable: data.repeatable,
				valueAtStart: number(data.valueAtStart),
				currentValue: number(data.currentValue),
				targetValue: number(data.targetValue),
				completionPercent: number(data.completionPercent),
				remainingAmount: number(data.remainingAmount),
				updatedAt: timestamp(data.updatedAt),
			};
		},
		1000,
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
