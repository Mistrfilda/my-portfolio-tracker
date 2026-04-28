<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\MarginOfSafety;

use App\Asset\Price\AssetPrice;

class StockValuationMarginOfSafety
{

	/**
	 * @param array<string> $reasons
	 */
	public function __construct(
		private AssetPrice|null $fairPriceEstimate,
		private float|null $marginPercentage,
		private float|null $sourceSpreadPercentage,
		private int $sourcesCount,
		private StockValuationMarginOfSafetyStatusEnum $status,
		private StockValuationMarginOfSafetyConfidenceEnum $confidence,
		private array $reasons,
	)
	{
	}

	public function getFairPriceEstimate(): AssetPrice|null
	{
		return $this->fairPriceEstimate;
	}

	public function getMarginPercentage(): float|null
	{
		return $this->marginPercentage;
	}

	public function getSourceSpreadPercentage(): float|null
	{
		return $this->sourceSpreadPercentage;
	}

	public function getSourcesCount(): int
	{
		return $this->sourcesCount;
	}

	public function getStatus(): StockValuationMarginOfSafetyStatusEnum
	{
		return $this->status;
	}

	public function getConfidence(): StockValuationMarginOfSafetyConfidenceEnum
	{
		return $this->confidence;
	}

	/**
	 * @return array<string>
	 */
	public function getReasons(): array
	{
		return $this->reasons;
	}

}
