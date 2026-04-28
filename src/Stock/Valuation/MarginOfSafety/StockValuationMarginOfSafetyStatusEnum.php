<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\MarginOfSafety;

enum StockValuationMarginOfSafetyStatusEnum: string
{

	case UNDERVALUED = 'undervalued';
	case FAIR = 'fair';
	case OVERVALUED = 'overvalued';
	case UNKNOWN = 'unknown';

	public function getLabel(): string
	{
		return match ($this) {
			self::UNDERVALUED => 'Undervalued',
			self::FAIR => 'Fair',
			self::OVERVALUED => 'Overvalued',
			self::UNKNOWN => 'N/A',
		};
	}

	public function getBadgeClasses(): string
	{
		return match ($this) {
			self::UNDERVALUED => 'bg-green-100 text-green-800 ring-green-600/20',
			self::FAIR => 'bg-blue-100 text-blue-800 ring-blue-600/20',
			self::OVERVALUED => 'bg-red-100 text-red-800 ring-red-600/20',
			self::UNKNOWN => 'bg-gray-100 text-gray-800 ring-gray-600/20',
		};
	}

}
