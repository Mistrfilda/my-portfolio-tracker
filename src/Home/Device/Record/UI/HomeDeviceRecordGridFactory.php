<?php

declare(strict_types = 1);

namespace App\Home\Device\Record\UI;

use App\Home\Device\HomeDevice;
use App\Home\Device\Record\HomeDeviceRecord;
use App\Home\Device\Record\HomeDeviceRecordRepository;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\DatagridFactory;
use App\UI\Control\Datagrid\Datasource\DoctrineDataSource;

class HomeDeviceRecordGridFactory
{

	public function __construct(
		private DatagridFactory $datagridFactory,
		private HomeDeviceRecordRepository $homeDeviceRecordRepository,
	)
	{
	}

	public function create(HomeDevice $homeDevice): Datagrid
	{
		$grid = $this->datagridFactory->create(
			new DoctrineDataSource(
				$this->homeDeviceRecordRepository->createQueryBuilderForDevice($homeDevice),
			),
		);

		$grid->setLimit(50);

		$grid->addColumnDatetime('createdAt', 'Čas měření')->setSortable();

		$grid->addColumnText(
			'floatValue',
			'Hodnota',
			static fn (HomeDeviceRecord $record): string => $record->getFloatValue() !== null ? (string) $record->getFloatValue() : '-',
		);

		$grid->addColumnText(
			'unit',
			'Jednotka',
			static fn (HomeDeviceRecord $record): string => $record->getUnit()?->format() ?? '-',
		);

		$grid->addColumnText('stringValue', 'Textová hodnota');

		$grid->addColumnText(
			'createdBy',
			'Vytvořil',
			static fn (HomeDeviceRecord $record): string => $record->getCreatedBy()?->getName() ?? 'Systém',
		);

		return $grid;
	}

}
