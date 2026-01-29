<?php

declare(strict_types = 1);

namespace App\Home\Device\UI;

use App\Home\Device\HomeDevice;
use App\Home\Device\HomeDeviceRepository;
use App\Home\Home;
use App\UI\Control\Datagrid\Action\DatagridActionParameter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\DatagridFactory;
use App\UI\Control\Datagrid\Datasource\DoctrineDataSource;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;

class HomeDeviceGridFactory
{

	public function __construct(
		private DatagridFactory $datagridFactory,
		private HomeDeviceRepository $homeDeviceRepository,
	)
	{
	}

	public function create(Home $home): Datagrid
	{
		$grid = $this->datagridFactory->create(
			new DoctrineDataSource(
				$this->homeDeviceRepository->createQueryBuilderForHome($home),
			),
		);

		$grid->setLimit(30);

		$name = $grid->addColumnText('name', 'Název');
		$name->setFilterText();
		$name->setSortable();

		$grid->addColumnText('internalId', 'Interní ID')->setFilterText();

		$grid->addColumnText(
			'type',
			'Typ',
			static fn (HomeDevice $device): string => $device->getType()->format(),
		);

		$grid->addColumnDatetime('createdAt', 'Vytvořeno')->setSortable();
		$grid->addColumnDatetime('updatedAt', 'Aktualizováno')->setSortable();

		$grid->addAction(
			'records',
			'Záznamy',
			'HomeDeviceRecord:default',
			[
				new DatagridActionParameter('id', 'id'),
			],
			SvgIcon::EYE,
			TailwindColorConstant::GREEN,
		);

		$grid->addAction(
			'edit',
			'Editovat',
			'HomeDeviceEdit:default',
			[
				new DatagridActionParameter('homeId', 'home', $home->getId()->toString()),
				new DatagridActionParameter('id', 'id'),
			],
			SvgIcon::PENCIL,
			TailwindColorConstant::BLUE,
		);

		return $grid;
	}

}
