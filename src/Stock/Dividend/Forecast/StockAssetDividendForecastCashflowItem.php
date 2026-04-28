<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Forecast;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;

class StockAssetDividendForecastCashflowItem
{

	public function __construct(
		private StockAsset $stockAsset,
		private CurrencyEnum $currency,
		private float $netAmount,
		private float $grossAmount,
		private float $netAmountInCzk,
		private float $grossAmountInCzk,
		private bool $confirmed,
	)
	{
	}

	public function getStockAsset(): StockAsset
	{
		return $this->stockAsset;
	}

	public function getCurrency(): CurrencyEnum
	{
		return $this->currency;
	}

	public function getNetAmount(): float
	{
		return $this->netAmount;
	}

	public function getGrossAmount(): float
	{
		return $this->grossAmount;
	}

	public function getNetAmountInCzk(): float
	{
		return $this->netAmountInCzk;
	}

	public function getGrossAmountInCzk(): float
	{
		return $this->grossAmountInCzk;
	}

	public function isConfirmed(): bool
	{
		return $this->confirmed;
	}

}
