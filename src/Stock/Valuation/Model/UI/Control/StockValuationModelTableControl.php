<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\UI\Control;

use App\Stock\Asset\StockAssetRepository;
use App\Stock\Valuation\StockValuationFacade;
use App\UI\Base\BaseControl;

class StockValuationModelTableControl extends BaseControl
{

	public function __construct(
		private StockValuationFacade $stockValuationFacade,
		private StockAssetRepository $stockAssetRepository,
	)
	{
	}

	public function render(): void
	{
		$template = $this->createTemplate(StockValuationModelTableControlTemplate::class);

		$items = [];
		$stockAssets = [];

		foreach ($this->stockAssetRepository->getAllActiveValuationAssets() as $stockAsset) {
			$items[] = new StockValuationModelTableControlItem(
				$stockAsset,
				$this->stockValuationFacade->getStockValuationsModelsForStockAsset($stockAsset),
			);

			$stockAssets[] = $stockAsset;
		}

		$template->tableControlItems = $items;
		$template->stockAssets = $stockAssets;
		$template->setFile(__DIR__ . '/StockValuationModelTableControl.latte');
		$template->render();
	}

}
