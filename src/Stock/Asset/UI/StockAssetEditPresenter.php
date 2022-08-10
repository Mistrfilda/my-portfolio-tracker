<?php

declare(strict_types = 1);

namespace App\Stock\Asset\UI;

use App\Stock\Asset\StockAssetRepository;
use App\UI\Base\BaseSysadminPresenter;
use App\UI\Control\Form\AdminForm;
use App\UI\FlashMessage\FlashMessageType;

class StockAssetEditPresenter extends BaseSysadminPresenter
{

	public function __construct(
		private readonly StockAssetRepository $stockAssetRepository,
		private readonly StockAssetFormFactory $stockAssetFormFactory,
	)
	{
		parent::__construct();
	}

	public function renderDefault(string|null $id): void
	{
		if ($id !== null) {
			$stockAsset = $this->stockAssetRepository->getById($this->processParameterRequiredUuid());
			$this->template->heading = sprintf('Úprava akcie %s', $stockAsset->getName());
		} else {
			$this->template->heading = 'Přidání nové akcie';
		}
	}

	protected function createComponentStockAssetForm(): AdminForm
	{
		$id = $this->processParameterUuid();

		$onSuccess = function () use ($id): void {
			if ($id === null) {
				$this->flashMessage('Akcie úspěšně vytvořena', FlashMessageType::SUCCESS);
			} else {
				$this->flashMessage('Akcie úspěšně upravena', FlashMessageType::SUCCESS);
			}

			$this->redirect('StockAsset:default');
		};

		return $this->stockAssetFormFactory->create($id, $onSuccess);
	}

}
