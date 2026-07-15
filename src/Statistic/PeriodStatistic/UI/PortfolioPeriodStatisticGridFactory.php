<?php

declare(strict_types = 1);

namespace App\Statistic\PeriodStatistic\UI;

use App\Statistic\PeriodStatistic\PortfolioPeriodStatistic;
use App\Statistic\PeriodStatistic\PortfolioPeriodStatisticRepository;
use App\UI\Control\Datagrid\Action\DatagridActionParameter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\DatagridFactory;
use App\UI\Control\Datagrid\Datasource\DoctrineDataSource;
use App\UI\Control\Datagrid\Sort\SortDirectionEnum;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;

class PortfolioPeriodStatisticGridFactory
{

	public function __construct(
		private DatagridFactory $datagridFactory,
		private PortfolioPeriodStatisticRepository $portfolioPeriodStatisticRepository,
	)
	{
	}

	public function create(): Datagrid
	{
		$grid = $this->datagridFactory->create(
			new DoctrineDataSource($this->portfolioPeriodStatisticRepository->createQueryBuilder()),
		);
		$grid->setLimit(10);
		$grid->addColumnDate('requestedStartAt', 'Od');
		$grid->addColumnDate('requestedEndAt', 'Do');
		$grid->addColumnBadge(
			'status',
			'Stav',
			TailwindColorConstant::BLUE,
			static fn (PortfolioPeriodStatistic $report): string => $report->getStatusLabel(),
		);
		$grid->addColumnDatetime('createdAt', 'Vytvořeno')->setSortable(SortDirectionEnum::DESC);
		$grid->addAction(
			'detail',
			'Detail',
			'PortfolioPeriodStatistic:detail',
			[new DatagridActionParameter('id', 'id')],
			SvgIcon::EYE,
			TailwindColorConstant::BLUE,
		);

		return $grid;
	}

}
