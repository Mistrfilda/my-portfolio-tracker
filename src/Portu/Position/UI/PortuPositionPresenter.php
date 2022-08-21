<?php

declare(strict_types = 1);

namespace App\Portu\Position\UI;

use App\Portu\Asset\PortuAssetRepository;
use App\UI\Base\BaseAdminPresenter;
use App\UI\Control\Datagrid\Datagrid;

class PortuPositionPresenter extends BaseAdminPresenter
{

	public function __construct(
		private readonly PortuAssetRepository $portuAssetRepository,
		private readonly PortuPositionGridFactory $portuPositionGridFactory,
	)
	{
		parent::__construct();
	}

	public function renderPositions(string $id): void
	{
		$portuAsset = $this->portuAssetRepository->getById($this->createUuidFromString($id));
		$this->template->heading = sprintf('Pozice - %s', $portuAsset->getName());
		$this->template->id = $id;
	}

	protected function createComponentPortuPositionGrid(): Datagrid
	{
		return $this->portuPositionGridFactory->create($this->processParameterRequiredUuid());
	}

}
