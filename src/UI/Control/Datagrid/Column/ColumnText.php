<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Column;

use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\Filter\FilterText;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\UuidInterface;
use function sprintf;

class ColumnText implements IColumn
{

	public const TEMPLATE_FILE = __DIR__ . '/templates/columnText.latte';

	/** @var callable|null */
	protected $getterMethod;

	public function __construct(
		protected Datagrid $datagrid,
		protected string $label,
		protected string $column,
		callable|null $getterMethod = null,
	)
	{
		$this->getterMethod = $getterMethod;
	}

	public function getColumn(): string
	{
		return $this->column;
	}

	public function getLabel(): string
	{
		return $this->label;
	}

	public function getDatagrid(): Datagrid
	{
		return $this->datagrid;
	}

	public function setFilterText(): FilterText
	{
		return $this->datagrid->setFilterText($this);
	}

	public function getGetterMethod(): callable|null
	{
		return $this->getterMethod;
	}

	public function getTemplate(): string
	{
		return self::TEMPLATE_FILE;
	}

	public function processValue(string|int|float|ImmutableDateTime|UuidInterface|null $value): string
	{
		if ($value instanceof ImmutableDateTime) {
			throw new DatagridColumnException(
				sprintf('Datetime object passed to column %s, use addColumnDatetime instead', $this->column),
			);
		}

		if ($value === null) {
			return Datagrid::NULLABLE_PLACEHOLDER;
		}

		if ($value instanceof UuidInterface) {
			return $value->toString();
		}

		return (string) $value;
	}

}
