<?php

declare(strict_types = 1);

namespace App\Stock\Valuation;

use App\Asset\Price\AssetPrice;
use App\Stock\AiAnalysis\StockAiAnalysisStockResultRepository;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Data\StockValuationData;
use App\Stock\Valuation\Data\StockValuationDataRepository;
use App\Stock\Valuation\Model\Consensus\StockValuationModelConsensus;
use App\Stock\Valuation\Model\Consensus\StockValuationModelConsensusProvider;

class StockValuationPriceProvider
{

	public function __construct(
		private readonly StockValuationFacade $stockValuationFacade,
		private readonly StockValuationModelConsensusProvider $stockValuationModelConsensusProvider,
		private readonly StockValuationDataRepository $stockValuationDataRepository,
		private readonly StockAiAnalysisStockResultRepository $stockAiAnalysisStockResultRepository,
	)
	{
	}

	public function getModelConsensus(StockAsset $stockAsset): StockValuationModelConsensus
	{
		$modelResponses = $this->stockValuationFacade->getStockValuationsModelsForStockAsset($stockAsset);

		return $this->stockValuationModelConsensusProvider->getForStockAsset(
			$stockAsset,
			$modelResponses,
		);
	}

	public function getModelConsensusPrice(StockAsset $stockAsset): AssetPrice|null
	{
		return $this->getModelConsensus($stockAsset)->getPrice();
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
