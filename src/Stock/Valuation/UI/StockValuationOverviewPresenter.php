<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\UI;

use App\Asset\Price\AssetPrice;
use App\Stock\AiAnalysis\StockAiAnalysisStockResultRepository;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Valuation\Data\StockValuationData;
use App\Stock\Valuation\Data\StockValuationDataRepository;
use App\Stock\Valuation\StockValuationFacade;
use App\Stock\Valuation\StockValuationTypeEnum;
use App\UI\Base\BaseAdminPresenter;

/**
 * @property-read StockValuationOverviewTemplate $template
 */
class StockValuationOverviewPresenter extends BaseAdminPresenter
{

	public function __construct(
		private StockAssetRepository $stockAssetRepository,
		private StockValuationFacade $stockValuationFacade,
		private StockValuationDataRepository $stockValuationDataRepository,
		private StockAiAnalysisStockResultRepository $stockAiAnalysisStockResultRepository,
	)
	{
		parent::__construct();
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'Valuační přehled akcií';
		$this->template->rows = [];
		$stockAssets = $this->stockAssetRepository->getAllActiveValuationAssets();
		usort(
			$stockAssets,
			static fn (StockAsset $left, StockAsset $right): int => $left->getName() <=> $right->getName(),
		);

		foreach ($stockAssets as $stockAsset) {
			$this->template->rows[] = new StockValuationOverviewRow(
				$stockAsset,
				[
					$this->createOverviewValue(
						'Price from all models',
						$this->getAverageModelPrice($stockAsset),
						$stockAsset,
					),
					$this->createOverviewValue('Analytics price', $this->getAnalyticsPrice($stockAsset), $stockAsset),
					$this->createOverviewValue(
						'AI analysis price',
						$this->getAiAnalysisPrice($stockAsset),
						$stockAsset,
					),
				],
			);
		}
	}

	private function getAverageModelPrice(StockAsset $stockAsset): AssetPrice|null
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

	private function getAnalyticsPrice(StockAsset $stockAsset): AssetPrice|null
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

	private function getAiAnalysisPrice(StockAsset $stockAsset): AssetPrice|null
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

	private function createOverviewValue(
		string $label,
		AssetPrice|null $assetPrice,
		StockAsset $stockAsset,
	): StockValuationOverviewValue
	{
		if ($assetPrice === null) {
			return new StockValuationOverviewValue($label, null, null);
		}

		$currentPrice = $stockAsset->getAssetCurrentPrice()->getPrice();
		$diffPercentage = $currentPrice === 0.0
			? null
			: ($assetPrice->getPrice() - $currentPrice) / $currentPrice * 100;

		return new StockValuationOverviewValue($label, $assetPrice, $diffPercentage);
	}

}
