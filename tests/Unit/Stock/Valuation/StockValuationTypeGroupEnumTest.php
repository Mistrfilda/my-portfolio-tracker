<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation;

use App\Stock\Valuation\StockValuationTypeEnum;
use App\Stock\Valuation\StockValuationTypeGroupEnum;
use PHPUnit\Framework\TestCase;

class StockValuationTypeGroupEnumTest extends TestCase
{

	public function testGetRenderableGroups(): void
	{
		self::assertSame(
			[
				StockValuationTypeGroupEnum::VALUATION,
				StockValuationTypeGroupEnum::FINANCIAL_HIGHLIGHTS,
				StockValuationTypeGroupEnum::BALANCE_SHEET,
				StockValuationTypeGroupEnum::CASH_FLOW,
				StockValuationTypeGroupEnum::TRADING_INFO,
				StockValuationTypeGroupEnum::DIVIDENDS,
				StockValuationTypeGroupEnum::ANALYST_INSIGHT,
			],
			StockValuationTypeGroupEnum::getRenderableGroups(),
		);
		self::assertNotContains(
			StockValuationTypeGroupEnum::BASIC_INFO,
			StockValuationTypeGroupEnum::getRenderableGroups(),
		);
	}

	public function testFormat(): void
	{
		self::assertSame('Basic Info', StockValuationTypeGroupEnum::BASIC_INFO->format());
		self::assertSame('Valuation', StockValuationTypeGroupEnum::VALUATION->format());
		self::assertSame('Financial Highlights', StockValuationTypeGroupEnum::FINANCIAL_HIGHLIGHTS->format());
		self::assertSame('Balance Sheet', StockValuationTypeGroupEnum::BALANCE_SHEET->format());
		self::assertSame('Cash Flow', StockValuationTypeGroupEnum::CASH_FLOW->format());
		self::assertSame('Trading Info', StockValuationTypeGroupEnum::TRADING_INFO->format());
		self::assertSame('Dividends', StockValuationTypeGroupEnum::DIVIDENDS->format());
		self::assertSame('Analyst Insight', StockValuationTypeGroupEnum::ANALYST_INSIGHT->format());
	}

	public function testGetTypes(): void
	{
		self::assertSame(
			[
				StockValuationTypeEnum::SYMBOL,
				StockValuationTypeEnum::COMPANY_NAME,
				StockValuationTypeEnum::CURRENT_PRICE,
				StockValuationTypeEnum::PRICE_CHANGE,
				StockValuationTypeEnum::PRICE_CHANGE_PERCENT,
				StockValuationTypeEnum::AFTER_HOURS_PRICE,
				StockValuationTypeEnum::AFTER_HOURS_CHANGE,
				StockValuationTypeEnum::AFTER_HOURS_CHANGE_PERCENT,
			],
			StockValuationTypeGroupEnum::BASIC_INFO->getTypes(),
		);
		self::assertSame(
			[
				StockValuationTypeEnum::MARKET_CAP,
				StockValuationTypeEnum::ENTERPRISE_VALUE,
				StockValuationTypeEnum::TRAILING_PE,
				StockValuationTypeEnum::FORWARD_PE,
				StockValuationTypeEnum::PEG_RATIO,
				StockValuationTypeEnum::PRICE_SALES,
				StockValuationTypeEnum::PRICE_BOOK,
				StockValuationTypeEnum::EV_REVENUE,
				StockValuationTypeEnum::EV_EBITDA,
			],
			StockValuationTypeGroupEnum::VALUATION->getTypes(),
		);
		self::assertSame(
			[
				StockValuationTypeEnum::REVENUE_TTM,
				StockValuationTypeEnum::REVENUE_PER_SHARE,
				StockValuationTypeEnum::QUARTERLY_REVENUE_GROWTH,
				StockValuationTypeEnum::GROSS_PROFIT,
				StockValuationTypeEnum::EBITDA,
				StockValuationTypeEnum::NET_INCOME,
				StockValuationTypeEnum::DILUTED_EPS,
				StockValuationTypeEnum::QUARTERLY_EARNINGS_GROWTH,
				StockValuationTypeEnum::PROFIT_MARGIN,
				StockValuationTypeEnum::OPERATING_MARGIN,
				StockValuationTypeEnum::RETURN_ON_ASSETS,
				StockValuationTypeEnum::RETURN_ON_EQUITY,
			],
			StockValuationTypeGroupEnum::FINANCIAL_HIGHLIGHTS->getTypes(),
		);
		self::assertSame(
			[
				StockValuationTypeEnum::TOTAL_CASH,
				StockValuationTypeEnum::TOTAL_CASH_PER_SHARE,
				StockValuationTypeEnum::TOTAL_DEBT,
				StockValuationTypeEnum::TOTAL_DEBT_EQUITY,
				StockValuationTypeEnum::CURRENT_RATIO,
				StockValuationTypeEnum::BOOK_VALUE_PER_SHARE,
			],
			StockValuationTypeGroupEnum::BALANCE_SHEET->getTypes(),
		);
		self::assertSame(
			[
				StockValuationTypeEnum::OPERATING_CASH_FLOW,
				StockValuationTypeEnum::LEVERED_FREE_CASH_FLOW,
			],
			StockValuationTypeGroupEnum::CASH_FLOW->getTypes(),
		);
		self::assertSame(
			[
				StockValuationTypeEnum::BETA,
				StockValuationTypeEnum::WEEK_52_CHANGE,
				StockValuationTypeEnum::WEEK_52_HIGH,
				StockValuationTypeEnum::WEEK_52_LOW,
				StockValuationTypeEnum::DAY_52_MA,
				StockValuationTypeEnum::DAY_200_MA,
				StockValuationTypeEnum::AVG_VOLUME_3M,
				StockValuationTypeEnum::SHARES_OUTSTANDING,
				StockValuationTypeEnum::FLOAT,
				StockValuationTypeEnum::HELD_BY_INSIDERS,
				StockValuationTypeEnum::HELD_BY_INSTITUTIONS,
			],
			StockValuationTypeGroupEnum::TRADING_INFO->getTypes(),
		);
		self::assertSame(
			[
				StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_RATE,
				StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_YIELD,
				StockValuationTypeEnum::TRAILING_ANNUAL_DIVIDEND_RATE,
				StockValuationTypeEnum::TRAILING_ANNUAL_DIVIDEND_YIELD,
				StockValuationTypeEnum::PAYOUT_RATIO,
				StockValuationTypeEnum::DIVIDEND_DATE,
				StockValuationTypeEnum::EX_DIVIDEND_DATE,
			],
			StockValuationTypeGroupEnum::DIVIDENDS->getTypes(),
		);
		self::assertSame(
			[
				StockValuationTypeEnum::ANALYST_PRICE_TARGET_LOW,
				StockValuationTypeEnum::ANALYST_PRICE_TARGET_AVERAGE,
				StockValuationTypeEnum::ANALYST_PRICE_TARGET_CURRENT,
				StockValuationTypeEnum::ANALYST_PRICE_TARGET_HIGH,
			],
			StockValuationTypeGroupEnum::ANALYST_INSIGHT->getTypes(),
		);
	}

	public function testEveryGroupTypeReferencesItsGroup(): void
	{
		foreach (StockValuationTypeGroupEnum::cases() as $group) {
			foreach ($group->getTypes() as $type) {
				self::assertSame($group, $type->getTypeGroup());
			}
		}
	}

}
