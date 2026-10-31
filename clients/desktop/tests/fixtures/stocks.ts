import type { StockPortfolio } from '../../src/shared/stocks';

// Synthetic portfolios only; no real account data.
export function stocksFixture(): StockPortfolio {
	return {
		generatedAt: '2026-10-31T12:00:00+01:00',
		currency: 'CZK',
		total: {
			investedAmount: 35_300,
			currentValue: 38_830.5,
			profit: 3530.5,
			profitPercent: 10.0014164306,
		},
		items: [
			{
				stock: {
					id: 'stock-tech',
					name: 'Example Technology',
					ticker: 'EXM',
					exchange: 'NASDAQ',
					currency: 'USD',
					currentPrice: 125.5,
					priceUpdatedAt: '2026-10-30T21:00:00Z',
				},
				pieces: 10,
				averagePurchasePrice: 100,
				amounts: {
					investedAmount: 1000,
					currentValue: 1255,
					profit: 255,
					profitPercent: 25.5,
				},
				brokerCurrency: 'CZK',
				brokerAmounts: {
					investedAmount: 23_000,
					currentValue: 28_990.5,
					profit: 5990.5,
					profitPercent: 26.0456521739,
				},
				currentValueCzk: 28_990.5,
				portfolioSharePercent: (28_990.5 / 38_830.5) * 100,
				dividends: {
					fromDate: '2025-10-31',
					grossPerShare: 3,
					yieldOnPricePercent: (3 / 125.5) * 100,
					yieldOnCostPercent: 3,
				},
				positions: [
					{
						id: 'position-tech-1',
						orderDate: '2025-06-10',
						pieces: 4,
						purchasePrice: 100,
						amounts: {
							investedAmount: 400,
							currentValue: 502,
							profit: 102,
							profitPercent: 25.5,
						},
						brokerCost: { amount: 9200, currency: 'CZK' },
					},
					{
						id: 'position-tech-2',
						orderDate: '2026-01-05',
						pieces: 6,
						purchasePrice: 100,
						amounts: {
							investedAmount: 600,
							currentValue: 753,
							profit: 153,
							profitPercent: 25.5,
						},
						brokerCost: { amount: 13_800, currency: 'CZK' },
					},
				],
			},
			{
				stock: {
					id: 'stock-energy',
					name: 'Česká energie',
					ticker: 'ENRG',
					exchange: 'XETRA',
					currency: 'EUR',
					currentPrice: 80,
					priceUpdatedAt: null,
				},
				pieces: 5,
				averagePurchasePrice: 100,
				amounts: {
					investedAmount: 500,
					currentValue: 400,
					profit: -100,
					profitPercent: -20,
				},
				brokerCurrency: 'EUR',
				brokerAmounts: {
					investedAmount: 500,
					currentValue: 400,
					profit: -100,
					profitPercent: -20,
				},
				currentValueCzk: 9840,
				portfolioSharePercent: (9840 / 38_830.5) * 100,
				dividends: {
					fromDate: '2025-10-31',
					grossPerShare: 0,
					yieldOnPricePercent: 0,
					yieldOnCostPercent: 0,
				},
				positions: [
					{
						id: 'position-energy-1',
						orderDate: '2026-02-01',
						pieces: 5,
						purchasePrice: 100,
						amounts: {
							investedAmount: 500,
							currentValue: 400,
							profit: -100,
							profitPercent: -20,
						},
						brokerCost: { amount: 500, currency: 'EUR' },
					},
				],
			},
		],
	};
}
