<?php

declare(strict_types = 1);

namespace App\Stock\Position\UI;

use App\Stock\Price\Downloader\Pse\PseDataDownloaderFacade;
use App\UI\Base\BaseAdminPresenter;
use App\UI\Control\Datagrid\Datagrid;

class StockPositionPresenter extends BaseAdminPresenter
{

	public function __construct(
		private readonly StockPositionGridFactory $stockPositionGridFactory,
		PseDataDownloaderFacade $pseDataDownloaderFacade
	)
	{
		parent::__construct();
		dump($pseDataDownloaderFacade->getPriceForAssets());
		die();
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'Akciové pozice';
	}

	protected function createComponentStockPositionGrid(): Datagrid
	{
		return $this->stockPositionGridFactory->create();
	}

}
