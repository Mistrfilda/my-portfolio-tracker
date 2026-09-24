<?php

declare(strict_types = 1);

namespace App\Stock\Asset\UI;

use App\JobRequest\JobRequestFacade;
use App\Stock\Asset\StockAssetRepository;
use App\UI\Base\BaseSysadminPresenter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\FlashMessage\FlashMessageType;
use Ramsey\Uuid\Uuid;

class StockAssetPresenter extends BaseSysadminPresenter
{

	public function __construct(
		private readonly StockAssetGridFactory $stockAssetGridFactory,
		private readonly JobRequestFacade $jobRequestFacade,
		private readonly StockAssetRepository $stockAssetRepository,
	)
	{
		parent::__construct();
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'Akcie';
	}

	public function handleDownloadData(string $id): void
	{
		$asset = $this->stockAssetRepository->getById(Uuid::fromString($id));
		$this->jobRequestFacade->addStockAssetDownloadToQueue($asset->getId()->toString());
		$this->flashMessage('Stažení dat akcie bylo zařazeno do fronty.', FlashMessageType::SUCCESS);
		$this->redirect('this');
	}

	protected function createComponentStockAssetGrid(): Datagrid
	{
		return $this->stockAssetGridFactory->create();
	}

}
