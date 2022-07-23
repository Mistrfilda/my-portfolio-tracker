<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Filter;

use App\UI\Control\Datagrid\Column\IColumn;

interface IFilter
{

	public function getType(): string;

	public function getColumn(): IColumn;

	public function getValue(): int|string|null;

	public function setValue(int|string $value): void;

	public function isValueSet(): bool;

}
