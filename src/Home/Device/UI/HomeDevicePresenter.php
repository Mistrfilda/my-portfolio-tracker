<?php

declare(strict_types = 1);

namespace App\Home\Device\UI;

use App\Home\Device\UI\Control\HomeDeviceOverviewControl;
use App\Home\Device\UI\Control\HomeDeviceOverviewControlFactory;
use App\Home\Home;
use App\Home\HomeRepository;
use App\UI\Base\BaseSysadminPresenter;
use App\UI\Control\Datagrid\Datagrid;

class HomeDevicePresenter extends BaseSysadminPresenter
{

	private Home $home;

	public function __construct(
		private readonly HomeRepository $homeRepository,
		private readonly HomeDeviceGridFactory $homeDeviceGridFactory,
		private readonly HomeDeviceOverviewControlFactory $homeDeviceOverviewControlFactory,
	)
	{
		parent::__construct();
	}

	public function actionDefault(string $homeId): void
	{
		$this->home = $this->homeRepository->getById($this->processParameterRequiredUuid('homeId'));
	}

	public function renderDefault(string $homeId): void
	{
		$this->template->heading = sprintf('Zařízení v domově %s', $this->home->getName());
		$this->template->home = $this->home;
	}

	protected function createComponentHomeDeviceGrid(): Datagrid
	{
		return $this->homeDeviceGridFactory->create($this->home);
	}

	protected function createComponentHomeDeviceOverview(): HomeDeviceOverviewControl
	{
		return $this->homeDeviceOverviewControlFactory->create($this->home);
	}

}
