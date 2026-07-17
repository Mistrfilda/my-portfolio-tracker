<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Filter;

class FilterBoolean extends FilterSelect
{

	public function __construct(
		string $key,
		string $label,
		string $column,
		string|null $referencedColumn = null,
	)
	{
		parent::__construct(
			$key,
			$label,
			$column,
			[
				1 => 'Ano',
				0 => 'Ne',
			],
			$referencedColumn,
		);
	}

	public function getType(): string
	{
		return FilterType::FILTER_BOOLEAN;
	}

}
