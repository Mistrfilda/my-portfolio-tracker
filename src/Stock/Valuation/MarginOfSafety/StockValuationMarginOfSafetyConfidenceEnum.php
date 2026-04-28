<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\MarginOfSafety;

enum StockValuationMarginOfSafetyConfidenceEnum: string
{

	case HIGH = 'high';
	case MEDIUM = 'medium';
	case LOW = 'low';
	case UNKNOWN = 'unknown';

	public function getLabel(): string
	{
		return match ($this) {
			self::HIGH => 'High confidence',
			self::MEDIUM => 'Medium confidence',
			self::LOW => 'Low confidence',
			self::UNKNOWN => 'Unknown confidence',
		};
	}

	public function getBadgeClasses(): string
	{
		return match ($this) {
			self::HIGH => 'bg-green-50 text-green-700 ring-green-600/20',
			self::MEDIUM => 'bg-amber-50 text-amber-700 ring-amber-600/20',
			self::LOW => 'bg-red-50 text-red-700 ring-red-600/20',
			self::UNKNOWN => 'bg-gray-50 text-gray-700 ring-gray-600/20',
		};
	}

}
