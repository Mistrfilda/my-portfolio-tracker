import type {
	StockHolding,
	StockPortfolio,
	StockPurchasePosition,
} from '../../shared/stocks';
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

function pieces(value: unknown): number {
	const count = number(value);
	return Number.isSafeInteger(count) ? count : invalid();
}

function position(value: unknown): StockPurchasePosition {
	const item = object(value);
	const brokerCost = object(item.brokerCost);
	return {
		id: string(item.id),
		orderDate: date(item.orderDate),
		pieces: pieces(item.pieces),
		purchasePrice: number(item.purchasePrice),
		amounts: amounts(item.amounts),
		brokerCost: {
			amount: number(brokerCost.amount),
			currency: currency(brokerCost.currency),
		},
	};
}

function holding(value: unknown): StockHolding {
	const item = object(value);
	const stock = object(item.stock);
	const dividends = object(item.dividends);
	return {
		stock: {
			id: string(stock.id),
			name: string(stock.name),
			ticker: string(stock.ticker),
			exchange: string(stock.exchange),
			currency: currency(stock.currency),
			currentPrice: number(stock.currentPrice),
			priceUpdatedAt: nullable(stock.priceUpdatedAt, timestamp),
		},
		pieces: pieces(item.pieces),
		averagePurchasePrice: number(item.averagePurchasePrice),
		amounts: amounts(item.amounts),
		brokerCurrency: currency(item.brokerCurrency),
		brokerAmounts: amounts(item.brokerAmounts),
		currentValueCzk: number(item.currentValueCzk),
		portfolioSharePercent: number(item.portfolioSharePercent),
		dividends: {
			fromDate: date(dividends.fromDate),
			grossPerShare: number(dividends.grossPerShare),
			yieldOnPricePercent: nullable(dividends.yieldOnPricePercent, number),
			yieldOnCostPercent: nullable(dividends.yieldOnCostPercent, number),
		},
		positions: list(item.positions, position, 10_000),
	};
}

export function parseStockPortfolio(value: unknown): StockPortfolio {
	const data = object(value);
	if (data.currency !== 'CZK') return invalid();
	return {
		generatedAt: timestamp(data.generatedAt),
		currency: 'CZK',
		total: amounts(data.total),
		items: list(data.items, holding, 1000),
	};
}
