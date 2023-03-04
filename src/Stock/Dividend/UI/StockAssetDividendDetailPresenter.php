<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\UI;

use App\Stock\Dividend\Record\StockAssetDividendRecordFacade;
use App\UI\Base\BaseSysadminPresenter;
use App\UI\Control\Datagrid\Datagrid;

class StockAssetDividendDetailPresenter extends BaseSysadminPresenter
{

	public function __construct(
		private StockAssetDividendGridFactory $stockAssetDividendGridFactory,
		StockAssetDividendRecordFacade $stockAssetDividendRecordFacade,
	)
	{
		parent::__construct();
		$stockAssetDividendRecordFacade->processAllDividends();
	}

	public function beforeRender(): void
	{
		parent::beforeRender();
		$this->template->heading = 'Dividendy';
	}

	protected function createComponentStockAssetDividendGrid(): Datagrid
	{
		return $this->stockAssetDividendGridFactory->create();
	}

}
