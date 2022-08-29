<?php

declare(strict_types = 1);

namespace App\Portu\Position\UI;

use App\Asset\Price\AssetPriceRenderer;
use App\Portu\Price\PortuAssetPriceRecord;
use App\Portu\Price\PortuAssetPriceRecordRepository;
use App\UI\Control\Datagrid\Action\DatagridActionParameter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\DatagridFactory;
use App\UI\Control\Datagrid\Datasource\DoctrineDataSource;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;
use Ramsey\Uuid\UuidInterface;

class PortuPositionPriceGridFactory
{

	public function __construct(
		private readonly DatagridFactory $datagridFactory,
		private readonly PortuAssetPriceRecordRepository $portuAssetPriceRecordRepository,
		private readonly AssetPriceRenderer $assetPriceRenderer,
	)
	{
	}

	public function create(UuidInterface $portuAssetId): Datagrid
	{
		$grid = $this->datagridFactory->create(
			new DoctrineDataSource(
				$this->portuAssetPriceRecordRepository->createQueryBuilderForDatagrid($portuAssetId),
			),
		);

		$grid->setLimit(30);

		$grid->addColumnDate('date', 'Datum')
			->setSortable();

		$grid->addColumnText(
			'totalInvestedAmount',
			'Celkově investováno',
			fn (PortuAssetPriceRecord $portuAssetPriceRecord): string => $this->assetPriceRenderer->getGridAssetPriceValue(
				$portuAssetPriceRecord->getTotalInvestedAmountAssetPrice(),
			),
		);

		$grid->addColumnText(
			'currentValueAmount',
			'Aktuální hodnota',
			fn (PortuAssetPriceRecord $portuAssetPriceRecord): string => $this->assetPriceRenderer->getGridAssetPriceValue(
				$portuAssetPriceRecord->getCurrentValueAssetPrice(),
			),
		);

		$grid->addAction(
			'editPrice',
			'Vytvořit novou hodnotu z dané pozice',
			'PortuPositionPrice:editPrice',
			[
				new DatagridActionParameter('portuPositionId', 'portuPositionId', $portuAssetId->toString()),
				new DatagridActionParameter('previousPortuPositionId', 'id'),
			],
			SvgIcon::PENCIL,
			TailwindColorConstant::GREEN,
		);

		return $grid;
	}

}
