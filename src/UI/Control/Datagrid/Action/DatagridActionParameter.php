<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Action;

class DatagridActionParameter
{

	public function __construct(
		private string $parameter,
		private string $referencedColumn,
		private string|null $rawValue = null,
	)
	{
	}

	public function getParameter(): string
	{
		return $this->parameter;
	}

	public function getReferencedColumn(): string
	{
		return $this->referencedColumn;
	}

	public function getRawValue(): string|null
	{
		return $this->rawValue;
	}

}
