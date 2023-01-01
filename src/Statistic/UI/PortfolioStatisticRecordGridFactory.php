<?php

declare(strict_types = 1);

namespace App\Statistic\UI;

use App\Statistic\PortfolioStatisticRecord;
use App\Statistic\PortfolioStatisticRecordRepository;
use App\UI\Control\Datagrid\Action\DatagridActionParameter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\DatagridFactory;
use App\UI\Control\Datagrid\Datasource\DoctrineDataSource;
use App\UI\Control\Datagrid\Sort\SortDirectionEnum;
use App\UI\Icon\SvgIcon;

class PortfolioStatisticRecordGridFactory
{

	public function __construct(
		private readonly DatagridFactory $datagridFactory,
		private readonly PortfolioStatisticRecordRepository $portfolioStatisticRecordRepository,
	)
	{
	}

	public function create(): Datagrid
	{
		$grid = $this->datagridFactory->create(
			new DoctrineDataSource($this->portfolioStatisticRecordRepository->createQueryBuilder()),
		);

		$grid->addColumnText('id', 'ID');
		$grid->addColumnDatetime('createdAt', 'Vytvořeno dne')
			->setSortable(SortDirectionEnum::DESC);

		$grid->addColumnText(
			'count',
			'Počet statistik',
			static fn (
				PortfolioStatisticRecord $portfolioStatisticRecord,
			): string => (string) $portfolioStatisticRecord->getPortfolioStatistics()->count(),
		);

		$grid->addAction(
			'detail',
			'Detail',
			'PortfolioStatistic:detail',
			[
				new DatagridActionParameter('id', 'id'),
			],
			SvgIcon::EYE,
		);

		return $grid;
	}

}
