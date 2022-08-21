<?php

declare(strict_types = 1);

namespace App\Portu\Position\UI;

use App\Portu\Position\PortuPositionRepository;
use App\UI\Base\BaseAdminPresenter;
use App\UI\Control\Form\AdminForm;
use App\UI\FlashMessage\FlashMessageType;

class PortuPositionEditPresenter extends BaseAdminPresenter
{

	public function __construct(
		private readonly PortuPositionFormFactory $portuPositionFormFactory,
		private readonly PortuPositionRepository $portuPositionRepository,
	)
	{
		parent::__construct();
	}

	public function renderEdit(string $portuAssetId, string|null $portuPositionId): void
	{
		if ($portuPositionId !== null) {
			$portuPosition = $this->portuPositionRepository->getById($this->processParameterRequiredUuid(
				'portuPositionId',
			));
			$this->template->heading = sprintf('Úprava portu pozice %s', $portuPosition->getPortuAsset()->getName());
		} else {
			$this->template->heading = 'Přidání nové portu pozice';
		}
	}

	protected function createComponentPortuPositionForm(): AdminForm
	{
		$id = $this->processParameterUuid('portuPositionId');
		$portuAssetId = $this->processParameterRequiredUuid('portuAssetId');

		$onSuccess = function () use ($id, $portuAssetId): void {
			if ($id === null) {
				$this->flashMessage('Portu pozice úspěšně vytvořena', FlashMessageType::SUCCESS);
			} else {
				$this->flashMessage('Portu pozice úspěšně upravena', FlashMessageType::SUCCESS);
			}

			$this->redirect('PortuPosition:positions', ['id' => $portuAssetId->toString()]);
		};

		return $this->portuPositionFormFactory->create(
			$id,
			$portuAssetId,
			$onSuccess,
		);
	}

}
