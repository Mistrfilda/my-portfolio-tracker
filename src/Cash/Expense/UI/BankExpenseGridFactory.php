<?php

declare(strict_types = 1);

namespace App\Cash\Expense\UI;

use App\Cash\Expense\Bank\BankExpense;
use App\Cash\Expense\Bank\BankExpenseRepository;
use App\UI\Control\Datagrid\Action\DatagridActionParameter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\DatagridFactory;
use App\UI\Control\Datagrid\Datasource\DoctrineDataSource;
use App\UI\Filter\ExpensePriceFilter;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;

class BankExpenseGridFactory
{

	public function __construct(
		private BankExpenseRepository $bankExpenseRepository,
		private DatagridFactory $datagridFactory,
	)
	{
	}

	public function create(): Datagrid
	{
		$grid = $this->datagridFactory->create(
			new DoctrineDataSource($this->bankExpenseRepository->createQueryBuilder()),
		);

		$grid->addColumnText('source', 'Zdroj');
		$grid->addColumnDatetime('settlementDate', 'Datum zúčtování')->setSortable();
		$grid->addColumnDatetime('transactionDate', 'Datum transakce')->setSortable();

		$grid->addColumnText('bankTransactionType', 'Typ transakce');

		$grid->addColumnText(
			'amount',
			'Hodnota',
			static fn (BankExpense $bankExpense): string => ExpensePriceFilter::format($bankExpense->getExpensePrice()),
		)->setSortable();

		$grid->addAction(
			'detail',
			'Detail',
			'showModal!',
			[new DatagridActionParameter('id', 'id')],
			SvgIcon::EYE,
			TailwindColorConstant::BLUE,
			isAjax: true,
		);

		return $grid;
	}

}
