<?php

declare(strict_types = 1);

namespace App\Home\UI;

use App\Home\HomeRepository;
use App\UI\Control\Datagrid\Action\DatagridActionParameter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\DatagridFactory;
use App\UI\Control\Datagrid\Datasource\DoctrineDataSource;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;

class HomeGridFactory
{

	public function __construct(
		private DatagridFactory $datagridFactory,
		private HomeRepository $homeRepository,
	)
	{
	}

	public function create(): Datagrid
	{
		$grid = $this->datagridFactory->create(
			new DoctrineDataSource(
				$this->homeRepository->createQueryBuilder(),
			),
		);

		$grid->setLimit(30);

		$name = $grid->addColumnText('name', 'Název');
		$name->setFilterText();
		$name->setSortable();

		$grid->addColumnDatetime('createdAt', 'Vytvořeno')->setSortable();
		$grid->addColumnDatetime('updatedAt', 'Aktualizováno')->setSortable();

		$grid->addAction(
			'edit',
			'Editovat',
			'HomeEdit:default',
			[
				new DatagridActionParameter('id', 'id'),
			],
			SvgIcon::PENCIL,
			TailwindColorConstant::BLUE,
		);

		$grid->addAction(
			'devices',
			'Zařízení',
			'HomeDevice:default',
			[
				new DatagridActionParameter('homeId', 'id'),
			],
			SvgIcon::COLLECTION,
			TailwindColorConstant::EMERALD,
		);

		return $grid;
	}

}
