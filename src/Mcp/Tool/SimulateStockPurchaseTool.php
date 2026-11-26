<?php

declare(strict_types = 1);

namespace App\Mcp\Tool;

use App\Admin\CurrentAppAdminGetter;
use App\Currency\CurrencyEnum;
use App\Mcp\Support\McpInvalidInputException;
use App\Mcp\Support\StockAssetResolver;
use App\Stock\Position\Simulation\StockPurchaseSimulationException;
use App\Stock\Position\Simulation\StockPurchaseSimulationService;
use const DATE_ATOM;

class SimulateStockPurchaseTool
{

	public function __construct(
		private readonly CurrentAppAdminGetter $currentAppAdminGetter,
		private readonly StockAssetResolver $stockAssetResolver,
		private readonly StockPurchaseSimulationService $simulationService,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function execute(
		string|null $ticker = null,
		string|null $stockAssetId = null,
		float|null $amount = null,
		string|null $amountCurrency = null,
		float|null $shares = null,
		float|null $priceOverride = null,
	): array
	{
		$parsedCurrency = null;
		if ($amountCurrency !== null && trim($amountCurrency) !== '') {
			$normalizedCurrency = strtoupper(trim($amountCurrency));
			$parsedCurrency = CurrencyEnum::tryFrom($normalizedCurrency);
			if ($parsedCurrency === null) {
				$allowed = array_map(
					static fn (CurrencyEnum $currency): string => $currency->value,
					CurrencyEnum::cases(),
				);

				throw new McpInvalidInputException(
					sprintf(
						'Unsupported amountCurrency "%s". Allowed values: %s.',
						$amountCurrency,
						implode(', ', $allowed),
					),
				);
			}
		}

		$owner = $this->currentAppAdminGetter->getAppAdmin();
		$asset = $this->stockAssetResolver->resolve($ticker, $stockAssetId);

		try {
			$result = $this->simulationService->simulate(
				$owner,
				$asset,
				$amount,
				$parsedCurrency,
				$shares,
				$priceOverride,
			);
		} catch (StockPurchaseSimulationException $e) {
			throw new McpInvalidInputException($e->getMessage(), 0, $e);
		}

		return [
			'generatedAt' => $result->generatedAt->format(DATE_ATOM),
			'readOnlySimulation' => true,
			'stock' => [
				'stockAssetId' => $result->stockAsset->getId()->toString(),
				'ticker' => $result->stockAsset->getTicker(),
				'name' => $result->stockAsset->getName(),
				'sector' => $result->sectorName,
				'currency' => $result->stockAsset->getCurrency()->value,
				'priceDownloadedAt' => $result->stockAsset->getPriceDownloadedAt()?->format(DATE_ATOM),
			],
			'simulation' => [
				'usedPrice' => round($result->usedPrice, 4),
				'usedPriceOverride' => $result->usedPriceOverride,
				'fxRateStockToCzk' => round($result->fxRateStockToCzk, 6),
				'inputAmount' => $result->inputAmount !== null ? round($result->inputAmount, 2) : null,
				'inputAmountCurrency' => $result->inputAmountCurrency?->value,
				'simulatedShares' => round($result->simulatedShares, 4),
				'wholeShares' => (int) floor($result->simulatedShares),
				'investedAmountInStockCurrency' => round($result->investedAmountInStockCurrency, 2),
				'investedAmountCzk' => round($result->investedAmountInCzk, 2),
			],
			'before' => [
				'shares' => $result->existingShares,
				'averagePurchasePrice' => $result->existingAveragePurchasePrice !== null
					? round($result->existingAveragePurchasePrice, 4)
					: null,
				'positionValueCzk' => round($result->existingPositionValueCzk, 2),
				'portfolioValueCzk' => round($result->existingPortfolioValueCzk, 2),
				'positionWeightPercent' => round($result->existingPositionWeightPercent, 2),
				'sectorValueCzk' => round($result->existingSectorValueCzk, 2),
				'sectorWeightPercent' => round($result->existingSectorWeightPercent, 2),
			],
			'after' => [
				'shares' => round($result->projectedShares, 4),
				'averagePurchasePrice' => round($result->projectedAveragePurchasePrice, 4),
				'positionValueCzk' => round($result->projectedPositionValueCzk, 2),
				'portfolioValueCzk' => round($result->projectedPortfolioValueCzk, 2),
				'positionWeightPercent' => round($result->projectedPositionWeightPercent, 2),
				'sectorValueCzk' => round($result->projectedSectorValueCzk, 2),
				'sectorWeightPercent' => round($result->projectedSectorWeightPercent, 2),
			],
			'delta' => [
				'positionWeightChangePercent' => round(
					$result->projectedPositionWeightPercent - $result->existingPositionWeightPercent,
					2,
				),
				'sectorWeightChangePercent' => round(
					$result->projectedSectorWeightPercent - $result->existingSectorWeightPercent,
					2,
				),
			],
			'estimatedAnnualDividend' => [
				'dividendPerShare' => $result->annualDividendPerShare !== null
					? round($result->annualDividendPerShare, 4)
					: null,
				'rateSource' => $result->dividendRateSource,
				'dividendTaxPercent' => $result->stockAsset->getDividendTax(),
				'grossInStockCurrency' => $result->estimatedAnnualGrossDividendInStockCurrency !== null
					? round($result->estimatedAnnualGrossDividendInStockCurrency, 2)
					: null,
				'netInStockCurrency' => $result->estimatedAnnualNetDividendInStockCurrency !== null
					? round($result->estimatedAnnualNetDividendInStockCurrency, 2)
					: null,
				'grossCzk' => $result->estimatedAnnualGrossDividendCzk !== null
					? round($result->estimatedAnnualGrossDividendCzk, 2)
					: null,
				'netCzk' => $result->estimatedAnnualNetDividendCzk !== null
					? round($result->estimatedAnnualNetDividendCzk, 2)
					: null,
				'grossYieldOnPurchasePercent' => $result->estimatedGrossYieldOnPurchasePercent !== null
					? round($result->estimatedGrossYieldOnPurchasePercent, 2)
					: null,
				'netYieldOnPurchasePercent' => $result->estimatedNetYieldOnPurchasePercent !== null
					? round($result->estimatedNetYieldOnPurchasePercent, 2)
					: null,
			],
			'warnings' => $result->warnings,
		];
	}

}
