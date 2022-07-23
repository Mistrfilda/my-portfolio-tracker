<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Filter;

use App\UI\Control\Datagrid\Column\ColumnText;

class FilterText implements IFilter
{

	private string|int|null $value = null;

	public function __construct(private ColumnText $column)
	{
	}

	public function setValue(int|string $value): void
	{
		$this->value = $value;
	}

	public function getType(): string
	{
		return FilterType::FILTER_TEXT;
	}

	public function getColumn(): ColumnText
	{
		return $this->column;
	}

	public function getValue(): int|string|null
	{
		return $this->value;
	}

	public function isValueSet(): bool
	{
		return $this->value !== null && $this->value !== '';
	}

}
