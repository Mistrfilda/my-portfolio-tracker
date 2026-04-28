<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\UI;

use App\Asset\Price\AssetPrice;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Valuation\MarginOfSafety\StockValuationMarginOfSafetyProvider;
use App\Stock\Valuation\StockValuationPriceProvider;
use App\UI\Base\BaseAdminPresenter;

/**
 * @property-read StockValuationOverviewTemplate $template
 */
class StockValuationOverviewPresenter extends BaseAdminPresenter
{

	public function __construct(
		private StockAssetRepository $stockAssetRepository,
		private StockValuationPriceProvider $stockValuationPriceProvider,
		private StockValuationMarginOfSafetyProvider $stockValuationMarginOfSafetyProvider,
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
			$averageModelPrice = $this->stockValuationPriceProvider->getAverageModelPrice($stockAsset);
			$analyticsPrice = $this->stockValuationPriceProvider->getAnalyticsPrice($stockAsset);
			$aiAnalysisPrice = $this->stockValuationPriceProvider->getAiAnalysisPrice($stockAsset);

			$this->template->rows[] = new StockValuationOverviewRow(
				$stockAsset,
				[
					$this->createOverviewValue(
						'Price from all models',
						$averageModelPrice,
						$stockAsset,
					),
					$this->createOverviewValue(
						'Analytics price',
						$analyticsPrice,
						$stockAsset,
					),
					$this->createOverviewValue(
						'AI analysis price',
						$aiAnalysisPrice,
						$stockAsset,
					),
				],
				$this->stockValuationMarginOfSafetyProvider->getForStockAsset(
					$stockAsset,
					$averageModelPrice,
					$analyticsPrice,
					$aiAnalysisPrice,
				),
			);
		}
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
