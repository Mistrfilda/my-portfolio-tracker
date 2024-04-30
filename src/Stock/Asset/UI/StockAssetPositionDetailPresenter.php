<?php

declare(strict_types = 1);

namespace App\Stock\Asset\UI;

use App\Stock\Asset\UI\Detail\StockAssetListDetailControl;
use App\Stock\Asset\UI\Detail\StockAssetListDetailControlEnum;
use App\Stock\Asset\UI\Detail\StockAssetListDetailControlFactory;
use App\Stock\Asset\UI\Detail\StockAssetListSummaryDetailControl;
use App\Stock\Asset\UI\Detail\StockAssetListSummaryDetailControlFactory;
use App\UI\Base\BaseAdminPresenter;

class StockAssetPositionDetailPresenter extends BaseAdminPresenter
{

	public function __construct(
		private readonly StockAssetListDetailControlFactory $stockPositionDetailControlFactory,
		private readonly StockAssetListSummaryDetailControlFactory $stockAssetSummaryDetailControlFactory,
	)
	{
		parent::__construct();
	}

	/**
	 * @param array<string> $ids
	 */
	public function renderDefault(array $ids = []): void
	{
		$this->template->heading = 'Detaily akciových pozic';
	}

	protected function createComponentStockAssetSummaryDetailControl(): StockAssetListSummaryDetailControl
	{
		return $this->stockAssetSummaryDetailControlFactory->create([], StockAssetListDetailControlEnum::OPEN_POSITIONS);
	}

	protected function createComponentStockPositionDetailControl(): StockAssetListDetailControl
	{
		return $this->stockPositionDetailControlFactory->create([], StockAssetListDetailControlEnum::OPEN_POSITIONS);
	}

}
