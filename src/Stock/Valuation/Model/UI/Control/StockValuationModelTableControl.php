<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\UI\Control;

use App\Stock\Asset\StockAssetRepository;
use App\Stock\Valuation\Model\UI\StockValuationModelSortService;
use App\Stock\Valuation\StockValuationFacade;
use App\UI\Base\BaseControl;

class StockValuationModelTableControl extends BaseControl
{

	private string|null $sortBy = null;

	private string $sortDirection = 'desc';

	public function __construct(
		private StockValuationFacade $stockValuationFacade,
		private StockAssetRepository $stockAssetRepository,
		private StockValuationModelSortService $sortService,
	)
	{
	}

	public function setSortParameters(string|null $sortBy, string $sortDirection): void
	{
		$this->sortBy = $sortBy;
		$this->sortDirection = $sortDirection;
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

		// Aplikuj sortování
		if ($this->sortBy !== null) {
			$items = $this->sortService->sortItems($items, $this->sortBy, $this->sortDirection);
		}

		$template->tableControlItems = $items;
		$template->stockAssets = $stockAssets;
		$template->sortBy = $this->sortBy;
		$template->sortDirection = $this->sortDirection;

		$template->setFile(__DIR__ . '/StockValuationModelTableControl.latte');
		$template->render();
	}

}
