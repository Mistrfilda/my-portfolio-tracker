<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model;

use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;

enum StockValuationModelState: string
{

	case FAIR_VALUE = 'fair_value';

	case OVERPRICED = 'overpriced';

	case UNDERPRICED = 'underpriced';

	case NEUTRAL = 'neutral';

	case UNABLE_TO_CALCULATE = 'unable_to_calculate';

	public function getTailwindColor(): string
	{
		return match ($this) {
			self::UNDERPRICED => TailwindColorConstant::GREEN,
			self::FAIR_VALUE => TailwindColorConstant::BLUE,
			self::OVERPRICED => TailwindColorConstant::RED,
			self::NEUTRAL => TailwindColorConstant::YELLOW,
			self::UNABLE_TO_CALCULATE => TailwindColorConstant::GRAY,
		};
	}

	public function getLabel(): string
	{
		return match ($this) {
			self::UNDERPRICED => 'Podhodnocená',
			self::FAIR_VALUE => 'Spravedlivá cena',
			self::OVERPRICED => 'Nadhodnocená',
			self::NEUTRAL => 'Neutrální',
			self::UNABLE_TO_CALCULATE => 'Nelze vypočítat',
		};
	}

	public function getDescription(): string
	{
		return match ($this) {
			self::UNDERPRICED => 'Akcie se obchoduje pod její spravedlivou hodnotou',
			self::FAIR_VALUE => 'Akcie se obchoduje za spravedlivou cenu',
			self::OVERPRICED => 'Akcie se obchoduje nad její spravedlivou hodnotou',
			self::NEUTRAL => 'Akcie je v neutrálním pásmu',
			self::UNABLE_TO_CALCULATE => 'Nedostatek dat pro výpočet modelu',
		};
	}

	public function getIcon(): string
	{
		return match ($this) {
			self::UNDERPRICED => SvgIcon::VALUATION_UNDERPRICED->value,
			self::FAIR_VALUE => SvgIcon::VALUATION_FAIR_VALUE->value,
			self::OVERPRICED => SvgIcon::VALUATION_OVERPRICED->value,
			self::NEUTRAL => SvgIcon::VALUATION_NEUTRAL->value,
			self::UNABLE_TO_CALCULATE => SvgIcon::VALUATION_UNABLE_TO_CALCULATE->value,
		};
	}

}
