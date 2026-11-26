<?php

declare(strict_types = 1);

namespace App\Stock\Position\Simulation;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

final readonly class StockPurchaseSimulationResult
{

	/**
	 * @param list<string> $warnings
	 */
	public function __construct(
		public ImmutableDateTime $generatedAt,
		public StockAsset $stockAsset,
		public float $usedPrice,
		public bool $usedPriceOverride,
		public float $simulatedShares,
		public float $investedAmountInStockCurrency,
		public float $investedAmountInCzk,
		public float $fxRateStockToCzk,
		public float|null $inputAmount,
		public CurrencyEnum|null $inputAmountCurrency,
		public string $sectorName,
		public int $existingShares,
		public float|null $existingAveragePurchasePrice,
		public float $existingPositionValueCzk,
		public float $existingPortfolioValueCzk,
		public float $existingPositionWeightPercent,
		public float $existingSectorValueCzk,
		public float $existingSectorWeightPercent,
		public float $projectedShares,
		public float $projectedAveragePurchasePrice,
		public float $projectedPositionValueCzk,
		public float $projectedPortfolioValueCzk,
		public float $projectedPositionWeightPercent,
		public float $projectedSectorValueCzk,
		public float $projectedSectorWeightPercent,
		public float|null $annualDividendPerShare,
		public string|null $dividendRateSource,
		public float|null $estimatedAnnualGrossDividendInStockCurrency,
		public float|null $estimatedAnnualNetDividendInStockCurrency,
		public float|null $estimatedAnnualGrossDividendCzk,
		public float|null $estimatedAnnualNetDividendCzk,
		public float|null $estimatedGrossYieldOnPurchasePercent,
		public float|null $estimatedNetYieldOnPurchasePercent,
		public array $warnings,
	)
	{
	}

}
