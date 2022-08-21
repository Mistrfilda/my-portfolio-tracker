<?php

declare(strict_types = 1);

namespace App\Portu\Asset\UI;

use App\Portu\Asset\PortuAsset;
use App\Portu\Asset\PortuAssetRepository;
use App\UI\Control\Datagrid\Action\DatagridActionParameter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\DatagridFactory;
use App\UI\Control\Datagrid\Datasource\DoctrineDataSource;
use App\UI\Filter\AssetPriceFilter;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;

class PortuAssetGridFactory
{

	public function __construct(
		private DatagridFactory $datagridFactory,
		private PortuAssetRepository $portuAssetRepository,
	)
	{
	}

	public function create(): Datagrid
	{
		$grid = $this->datagridFactory->create(
			new DoctrineDataSource(
				$this->portuAssetRepository->createQueryBuilder(),
			),
		);

		$grid->setLimit(30);

		$name = $grid->addColumnText('name', 'Jméno');
		$name->setFilterText();
		$name->setSortable();

		$grid->addColumnText('currency', 'Měna')->setSortable();

		$grid->addColumnText(
			'currentPrice',
			'Aktualní cena',
			static fn (PortuAsset $portuAsset): string => AssetPriceFilter::format($portuAsset->getAssetCurrentPrice()),
		);

		$grid->addAction(
			'edit',
			'Editovat',
			'PortuAssetEdit:default',
			[
				new DatagridActionParameter('id', 'id'),
			],
			SvgIcon::PENCIL,
			TailwindColorConstant::BLUE,
		);

		$grid->addAction(
			'positions',
			'Pozice',
			'PortuPosition:positions',
			[
				new DatagridActionParameter('id', 'id'),
			],
			SvgIcon::PENCIL,
			TailwindColorConstant::EMERALD,
		);

		return $grid;
	}

}
