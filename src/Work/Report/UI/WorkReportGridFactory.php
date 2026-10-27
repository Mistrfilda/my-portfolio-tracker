<?php

declare(strict_types = 1);

namespace App\Work\Report\UI;

use App\UI\Control\Datagrid\Action\DatagridActionParameter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\DatagridFactory;
use App\UI\Control\Datagrid\Datasource\DoctrineDataSource;
use App\UI\Control\Datagrid\Sort\SortDirectionEnum;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;
use App\Work\Report\WorkReport;
use App\Work\Report\WorkReportRepository;

class WorkReportGridFactory
{

	public function __construct(
		private readonly DatagridFactory $datagridFactory,
		private readonly WorkReportRepository $repository,
	)
	{
	}

	public function create(): Datagrid
	{
		$grid = $this->datagridFactory->create(new DoctrineDataSource($this->repository->createQueryBuilder()));
		$grid->setLimit(20);
		$grid->addColumnDate('reportDate', 'Pro standup')->setSortable(SortDirectionEnum::DESC);
		$grid->addColumnText('hours', 'Hodiny', static fn (WorkReport $report): string =>
			number_format($report->getData()['totals']['hours'], 2, ',', ' ') . ' h');
		$grid->addColumnText('entryCount', 'Záznamy', static fn (WorkReport $report): int =>
			$report->getData()['totals']['entryCount']);
		$grid->addColumnText('projectCount', 'Projekty', static fn (WorkReport $report): int =>
			$report->getData()['totals']['projectCount']);
		$grid->addColumnBadge(
			'status',
			'Stav',
			TailwindColorConstant::BLUE,
			static fn (WorkReport $report): string => $report->getData()['status'] === 'complete' ? 'Úplný' : 'Částečný',
		);
		$grid->addColumnDatetime('updatedAt', 'Aktualizováno');
		$grid->addAction(
			'detail',
			'Detail',
			'WorkReport:detail',
			[new DatagridActionParameter('id', 'id')],
			SvgIcon::EYE,
		);
		$grid->addAction(
			'pdf',
			'PDF',
			'WorkReport:downloadPdf',
			[new DatagridActionParameter('id', 'id')],
			SvgIcon::DOCUMENT_DUPLICATE,
		);
		return $grid;
	}

}
