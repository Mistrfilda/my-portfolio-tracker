import { describe, expect, it } from 'vitest';
import { filterHoldings } from '../../src/renderer/src/features/stocks/filter';
import { stocksFixture } from '../fixtures/stocks';

describe('stock portfolio filtering', () => {
	it('matches names without diacritics and ticker case, and combines currency filters', () => {
		const items = stocksFixture().items;
		expect(filterHoldings(items, ' ceska ', 'EUR', 'value-desc')).toEqual([
			items[1],
		]);
		expect(filterHoldings(items, 'exm', '', 'value-desc')).toEqual([items[0]]);
		expect(filterHoldings(items, 'EXM', 'EUR', 'value-desc')).toEqual([]);
	});
	it('sorts by comparable CZK values or broker percentage without changing portfolio shares or input', () => {
		const items = stocksFixture().items;
		const before = structuredClone(items);
		expect(filterHoldings(items, '', '', 'value-desc')).toEqual(items);
		expect(filterHoldings(items, '', '', 'profit-asc')).toEqual([
			items[1],
			items[0],
		]);
		expect(filterHoldings(items, '', '', 'profit-desc')).toEqual(items);
		expect(filterHoldings(items, '', '', 'name-asc')).toEqual([
			items[1],
			items[0],
		]);
		expect(items).toEqual(before);
	});
	it('sorts unavailable yields after actual zero yields', () => {
		const items = stocksFixture().items;
		items[0]!.dividends.yieldOnPricePercent = null;
		expect(filterHoldings(items, '', '', 'yield-desc')).toEqual([
			items[1],
			items[0],
		]);
	});
});
