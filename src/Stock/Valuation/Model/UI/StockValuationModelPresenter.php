<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\UI;

use App\Stock\Valuation\Model\UI\Control\StockValuationModelTableControl;
use App\Stock\Valuation\Model\UI\Control\StockValuationModelTableControlFactory;
use App\UI\Base\BaseAdminPresenter;

/**
 * @property-read StockValuationModelTemplate $template
 */
class StockValuationModelPresenter extends BaseAdminPresenter
{

	private string|null $sortBy = 'name';

	private string $sortDirection = 'asc';

	public function __construct(
		private StockValuationModelTableControlFactory $stockValuationModelTableControlFactory,
	)
	{
		parent::__construct();
	}

	public function actionDefault(
		string|null $sortBy = 'name',
		string $sortDirection = 'asc',
	): void
	{
		$this->sortBy = $sortBy;
		$this->sortDirection = $sortDirection;
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'Valuace akcií';
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
