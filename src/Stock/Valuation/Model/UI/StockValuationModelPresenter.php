<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\UI;

use App\Stock\Valuation\Model\UI\Control\StockValuationModelTableControl;
use App\Stock\Valuation\Model\UI\Control\StockValuationModelTableControlFactory;
use App\UI\Base\BaseAdminPresenter;

class StockValuationModelPresenter extends BaseAdminPresenter
{

	private string|null $sortBy = null;

	private string $sortDirection = 'desc';

	public function __construct(
		private StockValuationModelTableControlFactory $stockValuationModelTableControlFactory,
	)
	{
		parent::__construct();
	}

	public function actionDefault(
		string|null $sortBy = null,
		string $sortDirection = 'desc',
	): void
	{
		$this->sortBy = $sortBy;
		$this->sortDirection = $sortDirection;
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'Valuační modely akcií';
		$this->template->sortBy = $this->sortBy;
		$this->template->sortDirection = $this->sortDirection;

		if ($this->isAjax()) {
			$this->redrawControl('modelTable');
		}
	}

	protected function createComponentStockValuationTable(): StockValuationModelTableControl
	{
		$control = $this->stockValuationModelTableControlFactory->create();
		$control->setSortParameters($this->sortBy, $this->sortDirection);
		return $control;
	}

}
