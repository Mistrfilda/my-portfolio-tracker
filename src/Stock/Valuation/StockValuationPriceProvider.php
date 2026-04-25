<?php

declare(strict_types = 1);

namespace App\Stock\Valuation;

use App\Asset\Price\AssetPrice;
use App\Stock\AiAnalysis\StockAiAnalysisStockResultRepository;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Data\StockValuationData;
use App\Stock\Valuation\Data\StockValuationDataRepository;

class StockValuationPriceProvider
{

	public function __construct(
		private readonly StockValuationFacade $stockValuationFacade,
		private readonly StockValuationDataRepository $stockValuationDataRepository,
		private readonly StockAiAnalysisStockResultRepository $stockAiAnalysisStockResultRepository,
	)
	{
	}

	public function getAverageModelPrice(StockAsset $stockAsset): AssetPrice|null
	{
		$modelResponses = $this->stockValuationFacade->getStockValuationsModelsForStockAsset($stockAsset);
		$averagePrice = 0.0;
		$calculatedModelsCount = 0;

		foreach ($modelResponses as $modelResponse) {
			$assetPrice = $modelResponse->getAssetPrice();
			if ($assetPrice === null) {
				continue;
			}

			$averagePrice += $assetPrice->getPrice();
			$calculatedModelsCount++;
		}

		if ($calculatedModelsCount === 0) {
			return null;
		}

		return new AssetPrice(
			$stockAsset,
			$averagePrice / $calculatedModelsCount,
			$stockAsset->getCurrency(),
		);
	}

	public function getAnalyticsPrice(StockAsset $stockAsset): AssetPrice|null
	{
		$analyticsPrice = $this->stockValuationDataRepository->findTypesLatestForStockAsset(
			$stockAsset,
			[StockValuationTypeEnum::ANALYST_PRICE_TARGET_AVERAGE],
		)[StockValuationTypeEnum::ANALYST_PRICE_TARGET_AVERAGE->value] ?? null;

		if (!($analyticsPrice instanceof StockValuationData) || $analyticsPrice->getFloatValue() === null) {
			return null;
		}

		return new AssetPrice(
			$stockAsset,
			$analyticsPrice->getFloatValue(),
			$analyticsPrice->getCurrency(),
		);
	}

	public function getAiAnalysisPrice(StockAsset $stockAsset): AssetPrice|null
	{
		$aiResults = $this->stockAiAnalysisStockResultRepository->findLatestForStockAsset($stockAsset, 1);
		$aiResult = $aiResults[0] ?? null;

		if ($aiResult?->getFairPrice() === null) {
			return null;
		}

		$fairPriceCurrency = $aiResult->getFairPriceCurrency();
		if ($fairPriceCurrency === null) {
			return null;
		}

		return new AssetPrice(
			$stockAsset,
			$aiResult->getFairPrice(),
			$fairPriceCurrency,
		);
	}

}
