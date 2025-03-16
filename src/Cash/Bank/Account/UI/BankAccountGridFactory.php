<?php

declare(strict_types = 1);

namespace App\Cash\Bank\Account\UI;

use App\Cash\Bank\Account\BankAccountRepository;
use App\UI\Control\Datagrid\Action\DatagridActionParameter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\DatagridFactory;
use App\UI\Control\Datagrid\Datasource\DoctrineDataSource;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;

class BankAccountGridFactory
{

	public function __construct(
		private DatagridFactory $datagridFactory,
		private BankAccountRepository $bankAccountRepository,
	)
	{
	}

	public function create(): Datagrid
	{
		$grid = $this->datagridFactory->create(
			new DoctrineDataSource(
				$this->bankAccountRepository->createQueryBuilder(),
			),
		);

		$grid->addColumnText('id', 'ID');
		$grid->addColumnText('bank', 'Banka');
		$grid->addColumnText('name', 'Název');
		$grid->addColumnText('type', 'Typ');

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
		return $grid;
	}

}
