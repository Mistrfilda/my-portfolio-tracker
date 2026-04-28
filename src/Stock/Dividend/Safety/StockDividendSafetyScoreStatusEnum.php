<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Safety;

enum StockDividendSafetyScoreStatusEnum: string
{

	case SAFE = 'safe';
	case WATCH = 'watch';
	case RISKY = 'risky';

	public function getLabel(): string
	{
		return match ($this) {
			self::SAFE => 'Safe',
			self::WATCH => 'Watch',
			self::RISKY => 'Risky',
		};
	}

	public function getBadgeClass(): string
	{
		return match ($this) {
			self::SAFE => 'bg-green-100 text-green-800 ring-green-600/20',
			self::WATCH => 'bg-amber-100 text-amber-800 ring-amber-600/20',
			self::RISKY => 'bg-red-100 text-red-800 ring-red-600/20',
		};
	}

}
