<?php

declare(strict_types = 1);

namespace App\Home\Device\UI;

use App\Home\Device\HomeDeviceRepository;
use App\Home\Home;
use App\Home\HomeRepository;
use App\UI\Base\BaseSysadminPresenter;
use App\UI\Control\Form\AdminForm;
use App\UI\FlashMessage\FlashMessageType;
use Ramsey\Uuid\Uuid;

class HomeDeviceEditPresenter extends BaseSysadminPresenter
{

	/** @persistent */
	public string $homeId;

	private Home $home;

	public function __construct(
		private readonly HomeRepository $homeRepository,
		private readonly HomeDeviceRepository $homeDeviceRepository,
		private readonly HomeDeviceFormFactory $homeDeviceFormFactory,
	)
	{
		parent::__construct();
	}

	public function actionDefault(string $homeId, string|null $id = null): void
	{
		$this->home = $this->homeRepository->getById(Uuid::fromString($homeId));
	}

	public function renderDefault(string $homeId, string|null $id = null): void
	{
		if ($id !== null) {
			$homeDevice = $this->homeDeviceRepository->getById(Uuid::fromString($id));
			$this->template->heading = sprintf('Úprava zařízení %s', $homeDevice->getName());
		} else {
			$this->template->heading = 'Přidání nového zařízení';
		}

		$this->template->home = $this->home;
	}

	protected function createComponentHomeDeviceForm(): AdminForm
	{
		$id = $this->getParameter('id');
		assert($id === null || is_string($id));

		$onSuccess = function (): void {
			$this->flashMessage('Zařízení úspěšně uloženo', FlashMessageType::SUCCESS);
			$this->redirect('HomeDevice:default', ['homeId' => $this->home->getId()->toString()]);
		};

		return $this->homeDeviceFormFactory->create($this->home, $id, $onSuccess);
	}

}
