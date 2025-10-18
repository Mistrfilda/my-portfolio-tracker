<?php

declare(strict_types = 1);

namespace App\Goal\UI;

use App\Goal\PortfolioGoal;
use App\Goal\PortfolioGoalRepository;
use App\UI\Control\Datagrid\Action\DatagridActionParameter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\DatagridFactory;
use App\UI\Control\Datagrid\Datasource\DoctrineDataSource;
use App\UI\Filter\BooleanFilter;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;

class PortfolioGoalGridFactory
{

	public function __construct(
		private DatagridFactory $datagridFactory,
		private PortfolioGoalRepository $portfolioGoalRepository,
	)
	{
	}

	public function create(): Datagrid
	{
		$grid = $this->datagridFactory->create(
			new DoctrineDataSource(
				$this->portfolioGoalRepository->createQueryBuilder(),
			),
		);

		$grid->addColumnDate('startDate', 'Začátek cíle');
		$grid->addColumnDate('endDate', 'Konec cíle');
		$grid->addColumnDatetime('updatedAt', 'Aktualizováno');
		$grid->addColumnText('type', 'Typ cíle');
		$grid->addColumnBadge(
			'active',
			'Aktivní',
			TailwindColorConstant::CYAN,
			static fn (PortfolioGoal $portfolioGoal) => BooleanFilter::format($portfolioGoal->isActive()),
		);

		$grid->addColumnBadge('goal', 'Cílová částka', TailwindColorConstant::BLUE);
		$grid->addColumnBadge('repeatable', 'Opakování', TailwindColorConstant::GREEN);
		$grid->addColumnBadge('valueAtStart', 'Hodnota na začátku', TailwindColorConstant::YELLOW);
		$grid->addColumnBadge('currentValue', 'Aktuální hodnota', TailwindColorConstant::BLUE);
		$grid->addColumnBadge('valueAtEnd', 'Hodnota na konci', TailwindColorConstant::GREEN);

		$grid->addAction(
			'edit',
			'Editovat',
			'PortfolioGoal:edit',
			[
				new DatagridActionParameter('id', 'id'),
			],
			SvgIcon::PENCIL,
			TailwindColorConstant::BLUE,
		);

		$grid->addAction(
			'start',
			'Start',
			'startGoal!',
			[new DatagridActionParameter('id', 'id')],
			SvgIcon::EYE,
			TailwindColorConstant::GREEN,
		);

		$grid->addAction(
			'end',
			'Ukončit',
			'endGoal!',
			[new DatagridActionParameter('id', 'id')],
			SvgIcon::EYE_CLOSED,
			TailwindColorConstant::RED,
		);

		return $grid;
	}

}
