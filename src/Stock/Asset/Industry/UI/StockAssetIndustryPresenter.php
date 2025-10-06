<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Industry\UI;

use App\Stock\Asset\Industry\StockAssetIndustryRepository;
use App\UI\Base\BaseSysadminPresenter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Form\AdminForm;
use App\UI\FlashMessage\FlashMessageType;

class StockAssetIndustryPresenter extends BaseSysadminPresenter
{

	public function __construct(
		private readonly StockAssetIndustryGridFactory $stockAssetIndustryGridFactory,
		private readonly StockAssetIndustryFormFactory $stockAssetIndustryFormFactory,
		private readonly StockAssetIndustryRepository $stockAssetIndustryRepository,
	)
	{
		parent::__construct();
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'Akciové odvětví';
	}

	public function renderEdit(string|null $id): void
	{
		if ($id !== null) {
			$stockAssetIndustry = $this->stockAssetIndustryRepository->getById($this->processParameterRequiredUuid());
			$this->template->heading = sprintf('Úprava akciového odvětví %s', $stockAssetIndustry->getName());
		} else {
			$this->template->heading = 'Vytvoření akciového odvětví';
		}
	}

	protected function createComponentStockAssetIndustryGrid(): Datagrid
	{
		return $this->stockAssetIndustryGridFactory->create();
	}

	protected function createComponentStockAssetIndustryForm(): AdminForm
	{
		$id = $this->processParameterUuid();

		$onSuccess = function () use ($id): void {
			if ($id === null) {
				$this->flashMessage('Akciové odvětví úspěšně vytvořeno', FlashMessageType::SUCCESS);
			} else {
				$this->flashMessage('Akciové odvětví úspěšně upraveno', FlashMessageType::SUCCESS);
			}

			$this->redirect('StockAssetIndustry:default');
		};

		return $this->stockAssetIndustryFormFactory->create($id, $onSuccess);
	}

}
