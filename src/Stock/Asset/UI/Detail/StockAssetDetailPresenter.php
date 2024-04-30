<?php

declare(strict_types = 1);

namespace App\Stock\Asset\UI\Detail;

use App\Stock\Asset\StockAssetRepository;
use App\UI\Base\BaseSysadminPresenter;

class StockAssetDetailPresenter extends BaseSysadminPresenter
{

	public function __construct(
		private StockAssetRepository $stockAssetRepository,
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

}
