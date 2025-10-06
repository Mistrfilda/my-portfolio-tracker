<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Industry\UI;

use App\Stock\Asset\Industry\StockAssetIndustryRepository;
use App\UI\Control\Datagrid\Action\DatagridActionParameter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\DatagridFactory;
use App\UI\Control\Datagrid\Datasource\DoctrineDataSource;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;

class StockAssetIndustryGridFactory
{

	public function __construct(
		private StockAssetIndustryRepository $stockAssetIndustryRepository,
		private DatagridFactory $datagridFactory,
	)
	{
	}

	public function create(): Datagrid
	{
		$grid = $this->datagridFactory->create(
			new DoctrineDataSource($this->stockAssetIndustryRepository->createQueryBuilder()),
		);

		$grid->addColumnText('id', 'ID');
		$grid->addColumnText('name', 'Jméno')->setFilterText();
		$grid->addColumnText('mappingName', 'Jméno pro mapovaní');
		$grid->addColumnText('currentPERatio', 'Aktuální P/E ratio');

		$grid->addAction(
			'edit',
			'Editovat',
			'StockAssetIndustry:edit',
			[
				new DatagridActionParameter('id', 'id'),
			],
			SvgIcon::PENCIL,
			TailwindColorConstant::BLUE,
		);

		return $grid;
	}

}
