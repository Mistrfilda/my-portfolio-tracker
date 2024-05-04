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

	public function create(bool $onlyWithoutMainTag = false): Datagrid
	{
		$qb = $this->bankExpenseRepository->createQueryBuilder();
		if ($onlyWithoutMainTag) {
			$qb->andWhere(
				$qb->expr()->isNull('bankExpense.mainTag'),
			);
		}

		$grid = $this->datagridFactory->create(
			new DoctrineDataSource($qb),
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

		$grid->addColumnText('mainTag', 'Hlavní tag', static function (BankExpense $bankExpense): string {
			if ($bankExpense->getMainTag() !== null) {
				return sprintf(
					'%s (%s)',
					$bankExpense->getMainTag()->getName(),
					$bankExpense->getMainTag()->getExpenseCategory()?->getEnumName()->format(),
				);
			}

			return Datagrid::NULLABLE_PLACEHOLDER;
		}, 'mainTag.name')->setFilterText();

		$grid->addColumnText(
			'otherTags',
			'Počet ostatních tagů',
			static fn (BankExpense $bankExpense): string => (string) count($bankExpense->getOtherTags()),
		);

		$grid->addAction(
			'detail',
			'Detail',
			'showModal!',
			[new DatagridActionParameter('id', 'id')],
			SvgIcon::EYE,
			TailwindColorConstant::BLUE,
			isAjax: true,
		);

		$grid->addAction(
			'edit',
			'Editovat',
			'Expense:form',
			[new DatagridActionParameter('id', 'id')],
			SvgIcon::PENCIL,
			TailwindColorConstant::BLUE,
		);

		return $grid;
	}

}
