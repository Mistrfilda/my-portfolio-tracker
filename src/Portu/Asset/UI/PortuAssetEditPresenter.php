<?php

declare(strict_types = 1);

namespace App\Portu\Asset\UI;

use App\Portu\Asset\PortuAssetRepository;
use App\UI\Base\BaseSysadminPresenter;
use App\UI\Control\Form\AdminForm;
use App\UI\FlashMessage\FlashMessageType;

class PortuAssetEditPresenter extends BaseSysadminPresenter
{

	public function __construct(
		private readonly PortuAssetRepository $portuAssetRepository,
		private readonly PortuAssetFormFactory $portuAssetFormFactory,
	)
	{
		parent::__construct();
	}

	public function renderDefault(string|null $id): void
	{
		if ($id !== null) {
			$stockAsset = $this->portuAssetRepository->getById($this->processParameterRequiredUuid());
			$this->template->heading = sprintf('Úprava portu portfolia %s', $stockAsset->getName());
		} else {
			$this->template->heading = 'Přidání portu portfolia';
		}
	}

	protected function createComponentPortuAssetForm(): AdminForm
	{
		$id = $this->processParameterUuid();

		$onSuccess = function () use ($id): void {
			if ($id === null) {
				$this->flashMessage('Portu portfolio úspěšně vytvořeno', FlashMessageType::SUCCESS);
			} else {
				$this->flashMessage('Portu portfolio úspěšně upraveno', FlashMessageType::SUCCESS);
			}

			$this->redirect('PortuAsset:default');
		};

		return $this->portuAssetFormFactory->create($id, $onSuccess);
	}

}
