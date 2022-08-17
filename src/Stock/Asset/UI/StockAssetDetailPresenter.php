<?php

declare(strict_types = 1);

namespace App\Stock\Asset\UI;

use App\Stock\Asset\UI\Detail\StockAssetDetailControl;
use App\Stock\Asset\UI\Detail\StockAssetDetailControlFactory;
use App\Stock\Asset\UI\Detail\StockAssetSummaryDetailControl;
use App\Stock\Asset\UI\Detail\StockAssetSummaryDetailControlFactory;
use App\UI\Base\BaseAdminPresenter;

class StockAssetDetailPresenter extends BaseAdminPresenter
{

	public function __construct(
		private readonly StockAssetDetailControlFactory $stockPositionDetailControlFactory,
		private readonly StockAssetSummaryDetailControlFactory $stockAssetSummaryDetailControlFactory,
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

	protected function createComponentStockAssetSummaryDetailControl(): StockAssetSummaryDetailControl
	{
		return $this->stockAssetSummaryDetailControlFactory->create([]);
	}

	protected function createComponentStockPositionDetailControl(): StockAssetDetailControl
	{
		return $this->stockPositionDetailControlFactory->create([]);
	}

}
