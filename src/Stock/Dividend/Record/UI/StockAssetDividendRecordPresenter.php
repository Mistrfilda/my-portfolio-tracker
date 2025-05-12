<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Record\UI;

use App\Dashboard\DashboardDividendvalueBuilderFacade;
use App\Dashboard\UI\DashboardValueControl\DashboardValueControl;
use App\Dashboard\UI\DashboardValueControl\DashboardValueControlFactory;
use App\UI\Base\BaseAdminPresenter;
use App\UI\Control\Datagrid\Datagrid;

class StockAssetDividendRecordPresenter extends BaseAdminPresenter
{

	public function __construct(
		private StockAssetDividendRecordGridFactory $stockAssetDividendRecordGridFactory,
		private DashboardValueControlFactory $dashboardValueControlFactory,
		private DashboardDividendvalueBuilderFacade $dashboardDividendvalueBuilderFacade,
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

	protected function createComponentDashboardValueControl(): DashboardValueControl
	{
		return $this->dashboardValueControlFactory->create(
			$this->dashboardDividendvalueBuilderFacade,
		);
	}

}
