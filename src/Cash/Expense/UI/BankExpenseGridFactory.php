<?php

declare(strict_types = 1);

namespace App\Cash\Expense\UI;

use App\Cash\Expense\Bank\BankExpense;
use App\Cash\Expense\Bank\BankExpenseRepository;
use App\UI\Control\Datagrid\Action\DatagridActionParameter;
use App\UI\Control\Datagrid\Column\ColumnAlignmentEnum;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\DatagridFactory;
use App\UI\Control\Datagrid\Datasource\DoctrineDataSource;
use App\UI\Filter\CashPriceFilter;
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
		$grid->enableColumnSelection();
		$grid->setCompact();
		$grid->setActionsInDropdown();

		$grid->addColumnDate('transactionDate', 'Datum transakce')->setSortable();

		$amount = $grid->addColumnText(
			'amount',
			'Hodnota',
			static fn (BankExpense $bankExpense): string => CashPriceFilter::format($bankExpense->getExpensePrice()),
		);
		$amount->setAlignment(ColumnAlignmentEnum::RIGHT);
		$amount->setSortable();

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

		$grid->addColumnText('bankTransactionType', 'Typ transakce')->setSortable();
		$grid->addColumnText(
			'bankAccount',
			'Z bankovního účtu',
			static fn (BankExpense $bankExpense): string => $bankExpense->getBankAccount()->getFormattedName(),
		)->setMobileVisible(false);

		$settlementDate = $grid->addColumnDate('settlementDate', 'Datum zúčtování');
		$settlementDate
			->setDefaultVisible(false)
			->setMobileVisible(false);
		$settlementDate->setSortable();

		$grid->addColumnText('source', 'Zdroj')
			->setDefaultVisible(false)
			->setMobileVisible(false);

		$grid->addColumnText(
			'otherTags',
			'Počet ostatních tagů',
			static fn (BankExpense $bankExpense): string => (string) count($bankExpense->getOtherTags()),
		)
			->setDefaultVisible(false)
			->setMobileVisible(false)
			->setAlignment(ColumnAlignmentEnum::CENTER);

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

		$grid->addAction(
			'duplicate',
			'Duplikovat',
			'Expense:form',
			[new DatagridActionParameter('duplicateId', 'id')],
			SvgIcon::DOCUMENT_DUPLICATE,
			TailwindColorConstant::TEAL,
		);

		return $grid;
	}

}
