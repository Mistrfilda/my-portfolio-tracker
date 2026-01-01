<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Comparison\Industry;

use App\Stock\Valuation\StockValuationTypeEnum;

enum StockIndustryComparisonState: string
{

	case SIGNIFICANTLY_ABOVE = 'significantly_above'; // +20%
	case ABOVE_AVERAGE = 'above_average'; // +10% => +20%
	case IN_LINE = 'in_line'; // -10% => +10%
	case BELOW_AVERAGE = 'below_average'; // -20% => -10%
	case SIGNIFICANTLY_BELOW = 'significantly_below'; // -20% and less
	case NO_DATA = 'no_data';

	public function getLabel(): string
	{
		return match ($this) {
			self::SIGNIFICANTLY_ABOVE => 'Výrazně nad průměrem',
			self::ABOVE_AVERAGE => 'Nad průměrem odvětví',
			self::IN_LINE => 'V souladu s odvětvím',
			self::BELOW_AVERAGE => 'Pod průměrem odvětví',
			self::SIGNIFICANTLY_BELOW => 'Výrazně pod průměrem',
			self::NO_DATA => 'Nejsou dostupná data',
		};
	}

	public function isPositive(StockValuationTypeEnum $metric): bool
	{
		// Pro některé metriky je nižší hodnota lepší (např. P/E, Debt/Equity)
		$lowerIsBetter = in_array($metric, [
			StockValuationTypeEnum::TRAILING_PE,
			StockValuationTypeEnum::FORWARD_PE,
			StockValuationTypeEnum::PEG_RATIO,
			StockValuationTypeEnum::PRICE_SALES,
			StockValuationTypeEnum::PRICE_BOOK,
			StockValuationTypeEnum::TOTAL_DEBT_EQUITY,
		], true);

		if ($lowerIsBetter) {
			return in_array($this, [self::BELOW_AVERAGE, self::SIGNIFICANTLY_BELOW], true);
		}

		return in_array($this, [self::ABOVE_AVERAGE, self::SIGNIFICANTLY_ABOVE], true);
	}

}
