<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\Consensus;

use App\Asset\Price\AssetPrice;

class StockValuationModelConsensus
{

	/**
	 * @param array<string> $outlierModelLabels
	 * @param array<StockValuationModelFamilyEstimate> $familyEstimates
	 */
	public function __construct(
		private AssetPrice|null $price,
		private float|null $percentage,
		private AssetPrice|null $rawMedianPrice,
		private AssetPrice|null $arithmeticMeanPrice,
		private AssetPrice|null $lowerQuartilePrice,
		private AssetPrice|null $upperQuartilePrice,
		private float|null $lowerQuartilePercentage,
		private float|null $upperQuartilePercentage,
		private float|null $normalizedIqrPercentage,
		private float|null $normalizedMadPercentage,
		private int $validModelsCount,
		private int $totalModelsCount,
		private int $validFamiliesCount,
		private int $totalFamiliesCount,
		private array $outlierModelLabels,
		private array $familyEstimates,
		private StockValuationModelConsensusConfidenceEnum $confidence,
	)
	{
	}

	public function getPrice(): AssetPrice|null
	{
		return $this->price;
	}

	public function getPercentage(): float|null
	{
		return $this->percentage;
	}

	public function getRawMedianPrice(): AssetPrice|null
	{
		return $this->rawMedianPrice;
	}

	public function getArithmeticMeanPrice(): AssetPrice|null
	{
		return $this->arithmeticMeanPrice;
	}

	public function getLowerQuartilePrice(): AssetPrice|null
	{
		return $this->lowerQuartilePrice;
	}

	public function getUpperQuartilePrice(): AssetPrice|null
	{
		return $this->upperQuartilePrice;
	}

	public function getLowerQuartilePercentage(): float|null
	{
		return $this->lowerQuartilePercentage;
	}

	public function getUpperQuartilePercentage(): float|null
	{
		return $this->upperQuartilePercentage;
	}

	public function getNormalizedIqrPercentage(): float|null
	{
		return $this->normalizedIqrPercentage;
	}

	public function getNormalizedMadPercentage(): float|null
	{
		return $this->normalizedMadPercentage;
	}

	public function getValidModelsCount(): int
	{
		return $this->validModelsCount;
	}

	public function getTotalModelsCount(): int
	{
		return $this->totalModelsCount;
	}

	public function getValidFamiliesCount(): int
	{
		return $this->validFamiliesCount;
	}

	public function getTotalFamiliesCount(): int
	{
		return $this->totalFamiliesCount;
	}

	/**
	 * @return array<string>
	 */
	public function getOutlierModelLabels(): array
	{
		return $this->outlierModelLabels;
	}

	public function getOutlierCount(): int
	{
		return count($this->outlierModelLabels);
	}

	/**
	 * @return array<StockValuationModelFamilyEstimate>
	 */
	public function getFamilyEstimates(): array
	{
		return $this->familyEstimates;
	}

	public function getConfidence(): StockValuationModelConsensusConfidenceEnum
	{
		return $this->confidence;
	}

}
