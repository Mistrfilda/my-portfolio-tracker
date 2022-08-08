<?php

declare(strict_types = 1);

namespace App\Stock\Asset\UI;

use App\UI\Base\BaseSysadminPresenter;
use App\UI\Control\Datagrid\Datagrid;

class StockAssetPresenter extends BaseSysadminPresenter
{

	public function __construct(
		private readonly StockAssetGridFactory $stockAssetGridFactory,
	)
	{
		parent::__construct();
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'Akcie';
	}

	protected function createComponentStockAssetGrid(): Datagrid
	{
		return $this->stockAssetGridFactory->create();
	}

}
