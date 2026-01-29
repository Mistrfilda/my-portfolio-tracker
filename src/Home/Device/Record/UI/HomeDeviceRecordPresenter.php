<?php

declare(strict_types = 1);

namespace App\Home\Device\Record\UI;

use App\Home\Device\HomeDevice;
use App\Home\Device\HomeDeviceRepository;
use App\UI\Base\BaseSysadminPresenter;
use App\UI\Control\Datagrid\Datagrid;
use Ramsey\Uuid\Uuid;

class HomeDeviceRecordPresenter extends BaseSysadminPresenter
{

	private HomeDevice $homeDevice;

	public function __construct(
		private readonly HomeDeviceRepository $homeDeviceRepository,
		private readonly HomeDeviceRecordGridFactory $homeDeviceRecordGridFactory,
	)
	{
		parent::__construct();
	}

	public function actionDefault(string $id): void
	{
		$this->homeDevice = $this->homeDeviceRepository->getById(Uuid::fromString($id));
	}

	public function renderDefault(string $id): void
	{
		$this->template->homeDevice = $this->homeDevice;
		$this->template->heading = sprintf('Záznamy zařízení: %s', $this->homeDevice->getName());
	}

	protected function createComponentHomeDeviceRecordGrid(): Datagrid
	{
		return $this->homeDeviceRecordGridFactory->create($this->homeDevice);
	}

}
