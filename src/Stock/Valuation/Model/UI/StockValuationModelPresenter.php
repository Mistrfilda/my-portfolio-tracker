<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\UI;

use App\Stock\Valuation\Model\UI\Control\StockValuationModelTableControl;
use App\Stock\Valuation\Model\UI\Control\StockValuationModelTableControlFactory;
use App\UI\Base\BaseAdminPresenter;

class StockValuationModelPresenter extends BaseAdminPresenter
{

	public function __construct(
		private StockValuationModelTableControlFactory $stockValuationModelTableControlFactory,
	)
	{
		parent::__construct();
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'Valuační modely akcií';
	}

	protected function createComponentStockValuationTable(): StockValuationModelTableControl
	{
		return $this->stockValuationModelTableControlFactory->create();
	}

}
