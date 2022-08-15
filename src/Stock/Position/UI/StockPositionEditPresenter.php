<?php

declare(strict_types = 1);

namespace App\Stock\Position\UI;

use App\Stock\Position\StockPositionRepository;
use App\UI\Base\BaseAdminPresenter;
use App\UI\Control\Form\AdminForm;
use App\UI\FlashMessage\FlashMessageType;
use App\Utils\Datetime\DatetimeConst;

class StockPositionEditPresenter extends BaseAdminPresenter
{

	public function __construct(
		private readonly StockPositionRepository $stockPositionRepository,
		private readonly StockPositionFormFactory $stockPositionFormFactory,
	)
	{
		parent::__construct();
	}

	public function renderDefault(string|null $id): void
	{
		if ($id !== null) {
			$stockPosition = $this->stockPositionRepository->getById($this->processParameterRequiredUuid());
			$this->template->heading = sprintf(
				'Úprava pozice %s - %s',
				$stockPosition->getAsset()->getName(),
				$stockPosition->getOrderDate()->format(DatetimeConst::SYSTEM_DATE_FORMAT),
			);
		} else {
			$this->template->heading = 'Přidání nové pozice';
		}
	}

	protected function createComponentStockPositionForm(): AdminForm
	{
		$id = $this->processParameterUuid();

		$onSuccess = function () use ($id): void {
			if ($id === null) {
				$this->flashMessage('Pozice úspěšně vytvořena', FlashMessageType::SUCCESS);
			} else {
				$this->flashMessage('Pozice úspěšně upravena', FlashMessageType::SUCCESS);
			}

			$this->redirect('StockPosition:default');
		};

		return $this->stockPositionFormFactory->create($id, $onSuccess);
	}

}
