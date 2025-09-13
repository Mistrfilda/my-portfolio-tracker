<?php

declare(strict_types = 1);

namespace App\Stock\Valuation;

enum StockValuationTypeGroupEnum: string
{

	case BASIC_INFO = 'basic_info';
	case VALUATION = 'valuation';
	case FINANCIAL_HIGHLIGHTS = 'financial_highlights';
	case BALANCE_SHEET = 'balance_sheet';
	case CASH_FLOW = 'cash_flow';
	case TRADING_INFO = 'trading_info';
	case DIVIDENDS = 'dividends';

	/**
	 * @return array<StockValuationTypeGroupEnum>
	 */
	public static function getRenderableGroups(): array
	{
		return [
			StockValuationTypeGroupEnum::VALUATION,
			StockValuationTypeGroupEnum::FINANCIAL_HIGHLIGHTS,
			StockValuationTypeGroupEnum::BALANCE_SHEET,
			StockValuationTypeGroupEnum::CASH_FLOW,
			StockValuationTypeGroupEnum::TRADING_INFO,
			StockValuationTypeGroupEnum::DIVIDENDS,
		];
	}

	public function format(): string
	{
		return match ($this) {
			StockValuationTypeGroupEnum::BASIC_INFO => 'Basic Info',
			StockValuationTypeGroupEnum::VALUATION => 'Valuation',
			StockValuationTypeGroupEnum::FINANCIAL_HIGHLIGHTS => 'Financial Highlights',
			StockValuationTypeGroupEnum::BALANCE_SHEET => 'Balance Sheet',
			StockValuationTypeGroupEnum::CASH_FLOW => 'Cash Flow',
			StockValuationTypeGroupEnum::TRADING_INFO => 'Trading Info',
			StockValuationTypeGroupEnum::DIVIDENDS => 'Dividends',
		};
	}

	/**
	 * @return array<StockValuationTypeEnum>
	 */
	public function getTypes(): array
	{
		return match ($this) {
			StockValuationTypeGroupEnum::BASIC_INFO => [
				StockValuationTypeEnum::SYMBOL,
				StockValuationTypeEnum::COMPANY_NAME,
				StockValuationTypeEnum::CURRENT_PRICE,
				StockValuationTypeEnum::PRICE_CHANGE,
				StockValuationTypeEnum::PRICE_CHANGE_PERCENT,
				StockValuationTypeEnum::AFTER_HOURS_PRICE,
				StockValuationTypeEnum::AFTER_HOURS_CHANGE,
				StockValuationTypeEnum::AFTER_HOURS_CHANGE_PERCENT,
			],
			StockValuationTypeGroupEnum::VALUATION => [
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
			StockValuationTypeGroupEnum::FINANCIAL_HIGHLIGHTS => [
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
			StockValuationTypeGroupEnum::BALANCE_SHEET => [
				StockValuationTypeEnum::TOTAL_CASH,
				StockValuationTypeEnum::TOTAL_CASH_PER_SHARE,
				StockValuationTypeEnum::TOTAL_DEBT,
				StockValuationTypeEnum::TOTAL_DEBT_EQUITY,
				StockValuationTypeEnum::CURRENT_RATIO,
				StockValuationTypeEnum::BOOK_VALUE_PER_SHARE,
			],
			StockValuationTypeGroupEnum::CASH_FLOW => [
				StockValuationTypeEnum::OPERATING_CASH_FLOW,
				StockValuationTypeEnum::LEVERED_FREE_CASH_FLOW,
			],
			StockValuationTypeGroupEnum::TRADING_INFO => [
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
			StockValuationTypeGroupEnum::DIVIDENDS => [
				StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_RATE,
				StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_YIELD,
				StockValuationTypeEnum::TRAILING_ANNUAL_DIVIDEND_RATE,
				StockValuationTypeEnum::TRAILING_ANNUAL_DIVIDEND_YIELD,
				StockValuationTypeEnum::PAYOUT_RATIO,
				StockValuationTypeEnum::DIVIDEND_DATE,
				StockValuationTypeEnum::EX_DIVIDEND_DATE,
			]
		};
	}

}
