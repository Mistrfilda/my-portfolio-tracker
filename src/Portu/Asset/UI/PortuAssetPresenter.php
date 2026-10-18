<?php

declare(strict_types = 1);

namespace App\Portu\Asset\UI;

use App\UI\Base\BaseAdminPresenter;
use App\UI\Control\Datagrid\Datagrid;

/**
 * @property-read PortuAssetPresenterTemplate $template
 */
class PortuAssetPresenter extends BaseAdminPresenter
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
