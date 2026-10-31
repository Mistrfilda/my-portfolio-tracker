import type { StockHolding } from '../../../../shared/stocks';

export const stockSortOptions = {
	'value-desc': 'Nejvyšší hodnota',
	'name-asc': 'Název společnosti',
	'profit-desc': 'Nejvyšší zisk v %',
	'profit-asc': 'Nejnižší zisk v %',
	'yield-desc': 'Nejvyšší dividendový výnos',
};
export type StockSort = keyof typeof stockSortOptions;

function normalize(value: string): string {
	return value
		.normalize('NFD')
		.replace(/\p{M}/gu, '')
		.toLocaleLowerCase('cs-CZ');
}

export function filterHoldings(
	items: StockHolding[],
	query: string,
	currency: string,
	sort: StockSort,
): StockHolding[] {
	const term = normalize(query.trim());
	return items
		.filter(
			({ stock }) =>
				(!currency || stock.currency === currency) &&
				normalize(stock.name + ' ' + stock.ticker).includes(term),
		)
		.sort((a, b) => {
			let difference = 0;
			switch (sort) {
				case 'value-desc':
					difference = b.currentValueCzk - a.currentValueCzk;
					break;
				case 'profit-desc':
					difference =
						b.brokerAmounts.profitPercent - a.brokerAmounts.profitPercent;
					break;
				case 'profit-asc':
					difference =
						a.brokerAmounts.profitPercent - b.brokerAmounts.profitPercent;
					break;
				case 'yield-desc': {
					const left = a.dividends.yieldOnPricePercent;
					const right = b.dividends.yieldOnPricePercent;
					difference =
						left === null
							? right === null
								? 0
								: 1
							: right === null
								? -1
								: right - left;
					break;
				}
			}
			return (
				difference ||
				a.stock.name.localeCompare(b.stock.name, 'cs-CZ') ||
				a.stock.id.localeCompare(b.stock.id)
			);
		});
}
