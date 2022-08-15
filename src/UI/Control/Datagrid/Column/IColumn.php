<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Column;

use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\Sort\Sort;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\UuidInterface;

interface IColumn
{

	public function getDatagrid(): Datagrid;

	public function getLabel(): string;

	public function getColumn(): string;

	public function getTemplate(): string;

	public function getGetterMethod(): callable|null;

	public function processValue(
		string|int|float|ImmutableDateTime|UuidInterface|DatagridRenderableEnum|null $value,
	): string;

	public function getSort(): Sort|null;

	public function getReferencedColumn(): string|null;

}
