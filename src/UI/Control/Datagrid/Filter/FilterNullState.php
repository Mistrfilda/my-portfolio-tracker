<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Filter;

class FilterNullState extends FilterSelect
{

	public const NULL = 'null';

	public const NOT_NULL = 'not_null';

	public function __construct(
		string $key,
		string $label,
		string $column,
		string $nullLabel,
		string $notNullLabel,
		string|null $referencedColumn = null,
	)
	{
		parent::__construct(
			$key,
			$label,
			$column,
			[
				self::NULL => $nullLabel,
				self::NOT_NULL => $notNullLabel,
			],
			$referencedColumn,
		);
	}

	public function getType(): string
	{
		return FilterType::FILTER_NULL_STATE;
	}

}
