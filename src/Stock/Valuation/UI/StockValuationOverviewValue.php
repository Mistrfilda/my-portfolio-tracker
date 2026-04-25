<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\UI;

use App\Asset\Price\AssetPrice;

class StockValuationOverviewValue
{

	public function __construct(
		private string $label,
		private AssetPrice|null $assetPrice,
		private float|null $diffPercentage,
	)
	{
	}

	public function getLabel(): string
	{
		return $this->label;
	}

	public function getAssetPrice(): AssetPrice|null
	{
		return $this->assetPrice;
	}

	public function getDiffPercentage(): float|null
	{
		return $this->diffPercentage;
	}

	public function isPositive(): bool
	{
		return ($this->diffPercentage ?? 0.0) >= 0;
	}

	public function getBadgeClasses(): string
	{
		if ($this->diffPercentage === null) {
			return 'bg-gray-100 text-gray-500';
		}

		if ($this->isPositive()) {
			return 'bg-green-100 text-green-700';
		}

		return 'bg-red-100 text-red-700';
	}

}
