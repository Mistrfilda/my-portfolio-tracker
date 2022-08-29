<?php

declare(strict_types = 1);

namespace App\Portu\Position\UI;

use App\Portu\Position\PortuPositionRepository;
use App\UI\Base\BaseAdminPresenter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Form\AdminForm;

class PortuPositionPricePresenter extends BaseAdminPresenter
{

	public function __construct(
		private readonly PortuPositionPriceFormFactory $portuPositionPriceFormFactory,
		private readonly PortuPositionRepository $portuPositionRepository,
		private readonly PortuPositionPriceGridFactory $portuPositionPriceGridFactory,
	)
	{
		parent::__construct();
	}

	public function renderPrices(string $portuPositionId): void
	{
		$this->template->id = $portuPositionId;
	}

	public function renderEditPrice(string $portuPositionId, int|null $previousPortuPositionId): void
	{
		$portuPosition = $this->portuPositionRepository->getById($this->processParameterRequiredUuid(
			'portuPositionId',
		));

		$this->template->heading = sprintf(
			'Aktualizace ceny portu pozice %s',
			$portuPosition->getPortuAsset()->getName(),
		);
	}

	protected function createComponentPortuPositionPriceGrid(): Datagrid
	{
		return $this->portuPositionPriceGridFactory->create($this->processParameterRequiredUuid('portuPositionId'));
	}

	protected function createComponentPortuPositionPriceForm(): AdminForm
	{
		return $this->portuPositionPriceFormFactory->create(
			$this->processParameterRequiredUuid('portuPositionId'),
			$this->processParameterInt('previousPortuPositionId'),
			function (): void {
				$this->flashMessage('Cena úspěšně aktualizována');
				$this->redirect(
					'prices',
					['portuPositionId' => $this->processParameterRequiredUuid('portuPositionId')->toString()],
				);
			},
		);
	}

}
