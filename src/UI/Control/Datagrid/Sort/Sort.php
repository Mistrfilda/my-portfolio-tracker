<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Sort;

use App\UI\Control\Datagrid\Column\IColumn;

class Sort
{

	public function __construct(
		private IColumn $column,
		private SortDirectionEnum|null $currentDirection = null,
	)
	{
	}

	public function setSortDirection(SortDirectionEnum $currentDirection): void
	{
		$this->currentDirection = $currentDirection;
	}

	public function resetSortDirection(): void
	{
		$this->currentDirection = null;
	}

	public function getColumn(): IColumn
	{
		return $this->column;
	}

	public function getCurrentDirection(): SortDirectionEnum|null
	{
		return $this->currentDirection;
	}

}
