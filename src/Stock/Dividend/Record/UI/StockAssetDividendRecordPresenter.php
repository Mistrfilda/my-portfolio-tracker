<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Record\UI;

use App\UI\Base\BaseAdminPresenter;
use App\UI\Control\Datagrid\Datagrid;

class StockAssetDividendRecordPresenter extends BaseAdminPresenter
{

	public function __construct(
		private StockAssetDividendRecordGridFactory $stockAssetDividendRecordGridFactory,
	)
	{
		parent::__construct();
	}

	public function beforeRender(): void
	{
		parent::beforeRender();
		$this->template->heading = 'Vyplacené dividendy';
	}

	protected function createComponentStockAssetDividendRecordGrid(): Datagrid
	{
		return $this->stockAssetDividendRecordGridFactory->create();
	}

}
