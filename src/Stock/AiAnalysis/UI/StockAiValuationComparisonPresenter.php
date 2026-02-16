<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\UI;

use App\Stock\AiAnalysis\UI\Control\StockAiValuationComparisonControl;
use App\Stock\AiAnalysis\UI\Control\StockAiValuationComparisonControlFactory;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\UI\Base\BaseAdminPresenter;
use Ramsey\Uuid\Uuid;

class StockAiValuationComparisonPresenter extends BaseAdminPresenter
{

	private StockAsset $stockAsset;

	public function __construct(
		private StockAssetRepository $stockAssetRepository,
		private StockAiValuationComparisonControlFactory $stockAiValuationComparisonControlFactory,
	)
	{
		parent::__construct();
	}

	public function actionDetail(string $id): void
	{
		$this->stockAsset = $this->stockAssetRepository->getById(Uuid::fromString($id));
		$this->template->heading = 'Porovnání valuace: ' . $this->stockAsset->getName();
	}

	protected function createComponentStockAiValuationComparisonControl(): StockAiValuationComparisonControl
	{
		return $this->stockAiValuationComparisonControlFactory->create($this->stockAsset->getId());
	}

}
