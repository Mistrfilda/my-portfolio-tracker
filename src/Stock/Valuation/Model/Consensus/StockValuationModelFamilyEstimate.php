<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\Consensus;

use App\Asset\Price\AssetPrice;

class StockValuationModelFamilyEstimate
{

	public function __construct(
		private StockValuationModelFamilyEnum $family,
		private AssetPrice|null $price,
		private float|null $percentage,
		private int $validModelsCount,
		private int $totalModelsCount,
	)
	{
	}

	public function getFamily(): StockValuationModelFamilyEnum
	{
		return $this->family;
	}

	public function getPrice(): AssetPrice|null
	{
		return $this->price;
	}

	public function getPercentage(): float|null
	{
		return $this->percentage;
	}

	public function getValidModelsCount(): int
	{
		return $this->validModelsCount;
	}

	public function getTotalModelsCount(): int
	{
		return $this->totalModelsCount;
	}

}
