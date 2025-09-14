<?php

declare(strict_types = 1);

namespace App\Stock\Valuation;

use Nette\Utils\Strings;

enum StockValuationTypeEnum: string
{

	/**
	 * BASIC INFO
	 */
	case SYMBOL = 'symbol';
	case COMPANY_NAME = 'company_name';
	case CURRENT_PRICE = 'current_price';
	case PRICE_CHANGE = 'price_change';
	case PRICE_CHANGE_PERCENT = 'price_change_percent';
	case AFTER_HOURS_PRICE = 'after_hours_price';
	case AFTER_HOURS_CHANGE = 'after_hours_change';
	case AFTER_HOURS_CHANGE_PERCENT = 'after_hours_change_percent';

	/**
	 * VALUATION METRICS
	 */
	case MARKET_CAP = 'market_cap';
	case ENTERPRISE_VALUE = 'enterprise_value';
	case TRAILING_PE = 'trailing_pe';
	case FORWARD_PE = 'forward_pe';
	case PEG_RATIO = 'peg_ratio';
	case PRICE_SALES = 'price_sales';
	case PRICE_BOOK = 'price_book';
	case EV_REVENUE = 'ev_revenue';
	case EV_EBITDA = 'ev_ebitda';

	/**
	 * FINANCIAL HIGHLIGHTS
	 */
	case REVENUE_TTM = 'revenue_ttm';
	case REVENUE_PER_SHARE = 'revenue_per_share';
	case QUARTERLY_REVENUE_GROWTH = 'quarterly_revenue_growth';
	case GROSS_PROFIT = 'gross_profit';
	case EBITDA = 'ebitda';
	case NET_INCOME = 'net_income';
	case DILUTED_EPS = 'diluted_eps';
	case QUARTERLY_EARNINGS_GROWTH = 'quarterly_earnings_growth';
	case PROFIT_MARGIN = 'profit_margin';
	case OPERATING_MARGIN = 'operating_margin';
	case RETURN_ON_ASSETS = 'return_on_assets';
	case RETURN_ON_EQUITY = 'return_on_equity';

	/**
	 * BALANCE SHEET
	 */
	case TOTAL_CASH = 'total_cash';
	case TOTAL_CASH_PER_SHARE = 'total_cash_per_share';
	case TOTAL_DEBT = 'total_debt';
	case TOTAL_DEBT_EQUITY = 'total_debt_equity';
	case CURRENT_RATIO = 'current_ratio';
	case BOOK_VALUE_PER_SHARE = 'book_value_per_share';

	/**
	 * CASH FLOW
	 */
	case OPERATING_CASH_FLOW = 'operating_cash_flow';
	case LEVERED_FREE_CASH_FLOW = 'levered_free_cash_flow';

	/**
	 * TRADING INFO
	 */
	case BETA = 'beta';
	case WEEK_52_CHANGE = '52_week_change';
	case WEEK_52_HIGH = '52_week_high';
	case WEEK_52_LOW = '52_week_low';
	case DAY_52_MA = '50_day_ma';
	case DAY_200_MA = '200_day_ma';
	case AVG_VOLUME_3M = 'avg_volume_3m';
	case SHARES_OUTSTANDING = 'shares_outstanding';
	case FLOAT = 'float';
	case HELD_BY_INSIDERS = 'held_by_insiders';
	case HELD_BY_INSTITUTIONS = 'held_by_institutions';

	/**
	 * DIVIDENDS
	 */
	case FORWARD_ANNUAL_DIVIDEND_RATE = 'forward_annual_dividend_rate';
	case FORWARD_ANNUAL_DIVIDEND_YIELD = 'forward_annual_dividend_yield';
	case TRAILING_ANNUAL_DIVIDEND_RATE = 'trailing_annual_dividend_rate';
	case TRAILING_ANNUAL_DIVIDEND_YIELD = 'trailing_annual_dividend_yield';
	case PAYOUT_RATIO = 'payout_ratio';
	case DIVIDEND_DATE = 'dividend_date';
	case EX_DIVIDEND_DATE = 'ex_dividend_date';

	public function getTypeGroup(): StockValuationTypeGroupEnum
	{
		return match ($this) {
			StockValuationTypeEnum::SYMBOL,
			StockValuationTypeEnum::COMPANY_NAME,
			StockValuationTypeEnum::CURRENT_PRICE,
			StockValuationTypeEnum::PRICE_CHANGE,
			StockValuationTypeEnum::PRICE_CHANGE_PERCENT,
			StockValuationTypeEnum::AFTER_HOURS_PRICE,
			StockValuationTypeEnum::AFTER_HOURS_CHANGE,
			StockValuationTypeEnum::AFTER_HOURS_CHANGE_PERCENT => StockValuationTypeGroupEnum::BASIC_INFO,

			StockValuationTypeEnum::MARKET_CAP,
			StockValuationTypeEnum::ENTERPRISE_VALUE,
			StockValuationTypeEnum::TRAILING_PE,
			StockValuationTypeEnum::FORWARD_PE,
			StockValuationTypeEnum::PEG_RATIO,
			StockValuationTypeEnum::PRICE_SALES,
			StockValuationTypeEnum::PRICE_BOOK,
			StockValuationTypeEnum::EV_REVENUE,
			StockValuationTypeEnum::EV_EBITDA => StockValuationTypeGroupEnum::VALUATION,

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
			StockValuationTypeEnum::RETURN_ON_EQUITY => StockValuationTypeGroupEnum::FINANCIAL_HIGHLIGHTS,

			StockValuationTypeEnum::TOTAL_CASH,
			StockValuationTypeEnum::TOTAL_CASH_PER_SHARE,
			StockValuationTypeEnum::TOTAL_DEBT,
			StockValuationTypeEnum::TOTAL_DEBT_EQUITY,
			StockValuationTypeEnum::CURRENT_RATIO,
			StockValuationTypeEnum::BOOK_VALUE_PER_SHARE => StockValuationTypeGroupEnum::BALANCE_SHEET,

			StockValuationTypeEnum::OPERATING_CASH_FLOW,
			StockValuationTypeEnum::LEVERED_FREE_CASH_FLOW => StockValuationTypeGroupEnum::CASH_FLOW,

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
			StockValuationTypeEnum::HELD_BY_INSTITUTIONS => StockValuationTypeGroupEnum::TRADING_INFO,

			StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_RATE,
			StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_YIELD,
			StockValuationTypeEnum::TRAILING_ANNUAL_DIVIDEND_RATE,
			StockValuationTypeEnum::TRAILING_ANNUAL_DIVIDEND_YIELD,
			StockValuationTypeEnum::PAYOUT_RATIO,
			StockValuationTypeEnum::DIVIDEND_DATE,
			StockValuationTypeEnum::EX_DIVIDEND_DATE => StockValuationTypeGroupEnum::DIVIDENDS,
		};
	}

	public function getTypeValueType(): StockValuationTypeValueTypeEnum
	{
		return match ($this) {
			StockValuationTypeEnum::SYMBOL,
			StockValuationTypeEnum::COMPANY_NAME,
			StockValuationTypeEnum::DIVIDEND_DATE,
			StockValuationTypeEnum::EX_DIVIDEND_DATE=> StockValuationTypeValueTypeEnum::TEXT,

			StockValuationTypeEnum::PRICE_CHANGE_PERCENT,
			StockValuationTypeEnum::AFTER_HOURS_CHANGE_PERCENT,
			StockValuationTypeEnum::QUARTERLY_REVENUE_GROWTH,
			StockValuationTypeEnum::QUARTERLY_EARNINGS_GROWTH,
			StockValuationTypeEnum::PROFIT_MARGIN,
			StockValuationTypeEnum::OPERATING_MARGIN,
			StockValuationTypeEnum::RETURN_ON_ASSETS,
			StockValuationTypeEnum::RETURN_ON_EQUITY,
			StockValuationTypeEnum::WEEK_52_CHANGE,
			StockValuationTypeEnum::HELD_BY_INSIDERS,
			StockValuationTypeEnum::HELD_BY_INSTITUTIONS,
			StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_YIELD,
			StockValuationTypeEnum::TRAILING_ANNUAL_DIVIDEND_YIELD,
			StockValuationTypeEnum::PAYOUT_RATIO => StockValuationTypeValueTypeEnum::PERCENTAGE,

			default => StockValuationTypeValueTypeEnum::FLOAT,
		};
	}

	public function isCurrencyValue(): bool
	{
		return match ($this) {
			StockValuationTypeEnum::PRICE_CHANGE_PERCENT,
			StockValuationTypeEnum::AFTER_HOURS_CHANGE_PERCENT,
			StockValuationTypeEnum::FORWARD_PE,
			StockValuationTypeEnum::PEG_RATIO,
			StockValuationTypeEnum::TRAILING_PE,
			StockValuationTypeEnum::DIVIDEND_DATE,
			StockValuationTypeEnum::EX_DIVIDEND_DATE,
			StockValuationTypeEnum::PRICE_SALES,
			StockValuationTypeEnum::PRICE_BOOK,
			StockValuationTypeEnum::EV_EBITDA,
			StockValuationTypeEnum::CURRENT_RATIO,
			StockValuationTypeEnum::BOOK_VALUE_PER_SHARE,
			StockValuationTypeEnum::SHARES_OUTSTANDING,
			StockValuationTypeEnum::FLOAT,
			StockValuationTypeEnum::EV_REVENUE, => false,
			default => true,
		};
	}

	public function format(): string
	{
		return Strings::capitalize(str_replace('_', ' ', $this->value));
	}

}
