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
			'value',
			'Hodnota',
			static function (HomeDeviceRecord $record): string {
				if ($record->getBooleanValue() !== null) {
					return $record->getBooleanValue() ? 'Ano' : 'Ne';
				}

				if ($record->getFloatValue() !== null) {
					return (string) $record->getFloatValue();
				}

				if ($record->getStringValue() !== null) {
					return $record->getStringValue();
				}

				return '-';
			},
		);

		$grid->addColumnText(
			'unit',
			'Jednotka',
			static fn (HomeDeviceRecord $record): string => $record->getUnit()?->format() ?? '-',
		);

		$grid->addColumnText(
			'createdBy',
			'Vytvořil',
			static fn (HomeDeviceRecord $record): string => $record->getCreatedBy()?->getName() ?? 'Systém',
		);

		return $grid;
	}

}
