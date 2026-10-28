<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model;

enum StockValuationModelAvailability: string
{

	case AVAILABLE = 'available';
	case NOT_APPLICABLE = 'not_applicable';
	case MISSING_DATA = 'missing_data';
	case INVALID_DATA = 'invalid_data';

	public function getLabel(): string
	{
		return match ($this) {
			self::AVAILABLE => 'Vyhodnoceno',
			self::NOT_APPLICABLE => 'Nevhodný model',
			self::MISSING_DATA => 'Chybí vstup',
			self::INVALID_DATA => 'Neplatný vstup',
		};
	}

}
