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

}
