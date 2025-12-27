<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\UI\Control\Detail;

use App\Asset\Price\AssetPrice;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Valuation\Data\StockValuationDataRepository;
use App\Stock\Valuation\StockValuationFacade;
use App\Stock\Valuation\StockValuationTypeEnum;
use App\UI\Base\BaseControl;
use Ramsey\Uuid\UuidInterface;

class StockValuationDetailControl extends BaseControl
{

	public function __construct(
		private UuidInterface $stockAssetId,
		private StockValuationFacade $stockValuationFacade,
		private StockAssetRepository $stockAssetRepository,
		private StockValuationDataRepository $stockValuationDataRepository,
	)
	{
	}

	public function render(): void
	{
		$template = $this->createTemplate(StockValuationDetailControlTemplate::class);
		assert($template instanceof StockValuationDetailControlTemplate);

		$stockAsset = $this->stockAssetRepository->getById($this->stockAssetId);
		$template->stockAsset = $stockAsset;
		$template->stockValuation = $this->stockValuationFacade->getStockValuation($stockAsset->getId());
		$modelResponses = $this->stockValuationFacade->getStockValuationsModelsForStockAsset(
			$stockAsset,
		);
		$template->stockValuationModelResponses = $modelResponses;
		$template->stockValuationAnalyticsPrices = $this->stockValuationDataRepository->findTypesLatestForStockAsset(
			$stockAsset,
			[
				StockValuationTypeEnum::ANALYST_PRICE_TARGET_AVERAGE,
				StockValuationTypeEnum::ANALYST_PRICE_TARGET_LOW,
				StockValuationTypeEnum::ANALYST_PRICE_TARGET_HIGH,
			],
		);

		$averagePrice = 0;
		$calculatedModelsCount = 0;
		foreach ($modelResponses as $modelResponse) {
			$assetPrice = $modelResponse->getAssetPrice();
			if ($assetPrice !== null) {
				$averagePrice += $assetPrice->getPrice();
				$calculatedModelsCount++;
			}
		}

		if ($calculatedModelsCount > 0) {
			$averagePrice /= $calculatedModelsCount;
		}

		$template->averagePrice = new AssetPrice(
			$stockAsset,
			$averagePrice,
			$stockAsset->getCurrency(),
		);

		$template->setFile(__DIR__ . '/StockValuationDetailControl.latte');
		$template->render();

	}

}
