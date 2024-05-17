<?php

declare(strict_types = 1);

namespace App\Cash\Expense\Tag\UI;

use App\Cash\Expense\Tag\ExpenseTag;
use App\Cash\Expense\Tag\ExpenseTagRepository;
use App\UI\Control\Datagrid\Action\DatagridActionParameter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\DatagridFactory;
use App\UI\Control\Datagrid\Datasource\DoctrineDataSource;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;

class ExpenseTagDatagridFactory
{

	public function __construct(
		private ExpenseTagRepository $expenseTagRepository,
		private DatagridFactory $datagridFactory,
	)
	{
	}

	public function create(): Datagrid
	{
		$grid = $this->datagridFactory->create(
			new DoctrineDataSource($this->expenseTagRepository->createQueryBuilder()),
		);

		$grid->addColumnText('id', 'ID');
		$grid->addColumnText('name', 'Name')->setFilterText();

		$grid->setPerPage(30);

		$grid->addColumnText(
			'expenseCategory',
			'Kategorie',
			static fn (ExpenseTag $expenseTag): string => $expenseTag->getExpenseCategory() !== null
					? sprintf(
						'%s (%s)',
						$expenseTag->getExpenseCategory()->getName(),
						$expenseTag->getExpenseCategory()->getId(),
					)
					: Datagrid::NULLABLE_PLACEHOLDER,
		);

		$grid->addColumnText(
			'parentTag',
			'Parent tag',
			static fn (ExpenseTag $expenseTag): string => $expenseTag->getParentTag() !== null
					? sprintf(
						'%s (%s)',
						$expenseTag->getParentTag()->getName(),
						$expenseTag->getParentTag()->getId(),
					)
					: Datagrid::NULLABLE_PLACEHOLDER,
		);

		$grid->addAction(
			'edit',
			'Editovat',
			'ExpenseTag:editTag',
			[
				new DatagridActionParameter('id', 'id'),
			],
			SvgIcon::PENCIL,
			TailwindColorConstant::BLUE,
		);

		return $grid;
	}

}
