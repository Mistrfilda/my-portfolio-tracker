<?php

declare(strict_types = 1);

namespace App\Stock\Asset\UI\Detail;

use App\Stock\Asset\StockAssetRepository;
use App\Stock\Asset\UI\Detail\Control\StockAssetDetailControl;
use App\Stock\Asset\UI\Detail\Control\StockAssetDetailControlFactory;
use App\UI\Base\BaseSysadminPresenter;
use Nette\Application\Attributes\Persistent;

class StockAssetDetailPresenter extends BaseSysadminPresenter
{

	#[Persistent]
	public int $currentChartDays = 90;

	public function __construct(
		private StockAssetRepository $stockAssetRepository,
		private StockAssetDetailControlFactory $stockAssetDetailControlFactory,
	)
	{
		parent::__construct();
	}

	public function renderDetail(string $id, int $currentChartDays = 120): void
	{
		$stockAsset = $this->stockAssetRepository->getById($this->processParameterRequiredUuid());
		$this->template->stockAsset = $stockAsset;
		$this->template->heading = $stockAsset->getName();
	}

	public function handleChangeDays(int $currentChartDays): void
	{
		$this->currentChartDays = $currentChartDays;
		$this->redrawControl();
	}

	protected function createComponentStockAssetDetailControl(): StockAssetDetailControl
	{
		return $this->stockAssetDetailControlFactory->create(
			$this->processParameterRequiredUuid(),
			$this->processParameterInt('currentChartDays') ?? 90,
		);
	}

}
