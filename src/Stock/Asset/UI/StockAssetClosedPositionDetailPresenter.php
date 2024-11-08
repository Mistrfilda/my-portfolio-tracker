<?php

declare(strict_types = 1);

namespace App\Stock\Asset\UI;

use App\Stock\Asset\UI\Detail\List\StockAssetListDetailControl;
use App\Stock\Asset\UI\Detail\List\StockAssetListDetailControlEnum;
use App\Stock\Asset\UI\Detail\List\StockAssetListDetailControlFactory;
use App\Stock\Asset\UI\Detail\List\StockAssetListSummaryDetailControl;
use App\Stock\Asset\UI\Detail\List\StockAssetListSummaryDetailControlFactory;
use App\Stock\Position\Closed\UI\StockAssetClosedPositionListControl;
use App\Stock\Position\Closed\UI\StockAssetClosedPositionListControlFactory;
use App\UI\Base\BaseAdminPresenter;

class StockAssetClosedPositionDetailPresenter extends BaseAdminPresenter
{

	public function __construct(
		private readonly StockAssetListDetailControlFactory $stockPositionDetailControlFactory,
		private readonly StockAssetListSummaryDetailControlFactory $stockAssetSummaryDetailControlFactory,
		private readonly StockAssetClosedPositionListControlFactory $stockAssetClosedPositionListControlFactory,
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
		return $this->stockAssetSummaryDetailControlFactory->create(
			[],
			StockAssetListDetailControlEnum::CLOSED_POSITIONS,
		);
	}

	protected function createComponentStockPositionDetailControl(): StockAssetListDetailControl
	{
		return $this->stockPositionDetailControlFactory->create([], StockAssetListDetailControlEnum::CLOSED_POSITIONS);
	}

	protected function createComponentStockAssetClosedPositionListControl(): StockAssetClosedPositionListControl
	{
		return $this->stockAssetClosedPositionListControlFactory->create();
	}

}
