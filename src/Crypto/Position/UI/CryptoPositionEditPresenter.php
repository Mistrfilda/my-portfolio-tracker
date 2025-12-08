<?php

declare(strict_types = 1);

namespace App\Crypto\Position\UI;

use App\Crypto\Position\Closed\CryptoClosedPositionFormFactory;
use App\Crypto\Position\CryptoPositionRepository;
use App\UI\Base\BaseAdminPresenter;
use App\UI\Control\Form\AdminForm;
use App\UI\FlashMessage\FlashMessageType;
use App\Utils\Datetime\DatetimeConst;

class CryptoPositionEditPresenter extends BaseAdminPresenter
{

	public function __construct(
		private readonly CryptoPositionRepository $cryptoPositionRepository,
		private readonly CryptoPositionFormFactory $cryptoPositionFormFactory,
		private readonly CryptoClosedPositionFormFactory $cryptoClosedPositionFormFactory,
	)
	{
		parent::__construct();
	}

	public function renderDefault(string|null $id): void
	{
		if ($id !== null) {
			$cryptoPosition = $this->cryptoPositionRepository->getById($this->processParameterRequiredUuid());
			$this->template->heading = sprintf(
				'Úprava pozice %s - %s',
				$cryptoPosition->getAsset()->getName(),
				$cryptoPosition->getOrderDate()->format(DatetimeConst::SYSTEM_DATE_FORMAT),
			);
		} else {
			$this->template->heading = 'Přidání nové pozice';
		}
	}

	public function renderClosePosition(string $cryptoPositionId): void
	{
		$cryptoPosition = $this->cryptoPositionRepository->getById(
			$this->processParameterRequiredUuid('cryptoPositionId'),
		);

		if ($cryptoPosition->isPositionClosed()) {
			$cryptoClosedPosition = $cryptoPosition->getCryptoClosedPosition();
			$this->template->heading = sprintf(
				'Úprava uzavřené pozice pozice %s - %s',
				$cryptoClosedPosition?->getId()->toString(),
				$cryptoClosedPosition?->getDate()->format(DatetimeConst::SYSTEM_DATE_FORMAT),
			);
		} else {
			$this->template->heading = 'Uzavření pozice';
		}
	}

	protected function createComponentCryptoPositionForm(): AdminForm
	{
		$id = $this->processParameterUuid();

		$onSuccess = function () use ($id): void {
			if ($id === null) {
				$this->flashMessage('Pozice úspěšně vytvořena', FlashMessageType::SUCCESS);
			} else {
				$this->flashMessage('Pozice úspěšně upravena', FlashMessageType::SUCCESS);
			}

			$this->redirect('CryptoPosition:default');
		};

		return $this->cryptoPositionFormFactory->create($id, $onSuccess);
	}

	protected function createComponentClosedCryptoPositionForm(): AdminForm
	{
		$id = $this->processParameterUuid();

		$onSuccess = function () use ($id): void {
			if ($id === null) {
				$this->flashMessage('Uzavřená pozice úspěšně vytvořena', FlashMessageType::SUCCESS);
			} else {
				$this->flashMessage('Uzavřená pozice úspěšně upravena', FlashMessageType::SUCCESS);
			}

			$this->redirect('CryptoPosition:default');
		};

		return $this->cryptoClosedPositionFormFactory->create(
			$this->processParameterRequiredUuid('cryptoPositionId'),
			$onSuccess,
		);
	}

}
