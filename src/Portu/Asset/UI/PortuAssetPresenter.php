<?php

declare(strict_types = 1);

namespace App\Portu\Asset\UI;

use App\UI\Base\BaseSysadminPresenter;
use App\UI\Control\Datagrid\Datagrid;

class PortuAssetPresenter extends BaseSysadminPresenter
{

	public function __construct(
		private readonly PortuAssetGridFactory $portuAssetGridFactory,
	)
	{
		parent::__construct();
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'Portu porfolia';
	}

	protected function createComponentPortuAssetGrid(): Datagrid
	{
		return $this->portuAssetGridFactory->create();
	}

}
