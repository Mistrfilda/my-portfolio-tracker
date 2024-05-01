<?php

declare(strict_types = 1);

namespace App\Stock\Asset\UI\Detail;

use App\Stock\Asset\StockAssetRepository;
use App\Stock\Asset\UI\Detail\Control\StockAssetDetailControl;
use App\Stock\Asset\UI\Detail\Control\StockAssetDetailControlFactory;
use App\UI\Base\BaseSysadminPresenter;

class StockAssetDetailPresenter extends BaseSysadminPresenter
{

	public function __construct(
		private StockAssetRepository $stockAssetRepository,
		private StockAssetDetailControlFactory $stockAssetDetailControlFactory,
	)
	{
		parent::__construct();
	}

	public function renderDetail(string $id): void
	{
		$stockAsset = $this->stockAssetRepository->getById($this->processParameterRequiredUuid());
		$this->template->stockAsset = $stockAsset;
		$this->template->heading = $stockAsset->getName();
	}

	protected function createComponentStockAssetDetailControl(): StockAssetDetailControl
	{
		return $this->stockAssetDetailControlFactory->create($this->processParameterRequiredUuid());
	}

}
