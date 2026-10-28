<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model;

enum StockValuationModelPurpose: string
{

	case PRICE = 'price';
	case REFERENCE = 'reference';
	case DIAGNOSTIC = 'diagnostic';

	public static function forModel(StockValuationModel $model): self
	{
		return match (true) {
			$model instanceof Price\RoeQualityValuationModel,
			$model instanceof Price\DebtAdjustedValuationModel,
			$model instanceof Price\DividendPayoutSafetyModel,
			$model instanceof Price\PegRatioValuationModel => self::DIAGNOSTIC,
			$model instanceof Price\GrahamNumberValuationModel,
			$model instanceof Price\PriceToSalesValuationModel,
			$model instanceof Price\DividendYieldFairValueModel => self::REFERENCE,
			default => self::PRICE,
		};
	}

	public function getLabel(): string
	{
		return match ($this) {
			self::PRICE => 'V konsenzu',
			self::REFERENCE => 'Doplňkový odhad',
			self::DIAGNOSTIC => 'Ukazatel, ne cena',
		};
	}

}
