<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\Consensus;

enum StockValuationModelFamilyEnum: string
{

	case ASSET = 'asset';
	case EARNINGS = 'earnings';
	case CASH_FLOW = 'cash_flow';
	case SALES = 'sales';
	case DIVIDEND = 'dividend';
	case OTHER = 'other';

	public function getLabel(): string
	{
		return match ($this) {
			self::ASSET => 'Majetek a rozvaha',
			self::EARNINGS => 'Ziskovost',
			self::CASH_FLOW => 'Cash flow a podniková hodnota',
			self::SALES => 'Tržby',
			self::DIVIDEND => 'Dividendy',
			self::OTHER => 'Ostatní',
		};
	}

}
