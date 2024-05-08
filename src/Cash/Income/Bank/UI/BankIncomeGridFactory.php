<?php

declare(strict_types = 1);

namespace App\Cash\Income\Bank\UI;

use App\Cash\Income\Bank\BankIncome;
use App\Cash\Income\Bank\BankIncomeRepository;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\DatagridFactory;
use App\UI\Control\Datagrid\Datasource\DoctrineDataSource;
use App\UI\Filter\CashPriceFilter;

class BankIncomeGridFactory
{

	public function __construct(
		private BankIncomeRepository $bankIncomeRepository,
		private DatagridFactory $datagridFactory,
	)
	{
	}

	public function create(): Datagrid
	{
		$qb = $this->bankIncomeRepository->createQueryBuilder();
		$grid = $this->datagridFactory->create(
			new DoctrineDataSource($qb),
		);

		$grid->addColumnText('source', 'Zdroj');
		$grid->addColumnDatetime('settlementDate', 'Datum zúčtování')->setSortable();

		$grid->addColumnText('bankTransactionType', 'Typ transakce');

		$grid->addColumnText(
			'amount',
			'Hodnota',
			static fn (BankIncome $bankIncome): string => CashPriceFilter::format($bankIncome->getExpensePrice()),
		)->setSortable();

		return $grid;
	}

}
