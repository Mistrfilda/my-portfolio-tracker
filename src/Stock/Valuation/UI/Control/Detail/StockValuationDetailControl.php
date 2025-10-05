<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\UI\Control\Detail;

use App\Stock\Asset\StockAssetRepository;
use App\Stock\Valuation\StockValuationFacade;
use App\UI\Base\BaseControl;
use Ramsey\Uuid\UuidInterface;

class StockValuationDetailControl extends BaseControl
{

	public function __construct(
		private UuidInterface $stockAssetId,
		private StockValuationFacade $stockValuationFacade,
		private StockAssetRepository $stockAssetRepository,
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
		$template->stockValuationModelResponses = $this->stockValuationFacade->getStockValuationsModelsForStockAsset(
			$stockAsset,
		);

		$template->setFile(__DIR__ . '/StockValuationDetailControl.latte');
		$template->render();

	}

}
