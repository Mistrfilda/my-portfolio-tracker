<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Column;

use App\UI\Control\Datagrid\Datagrid;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\UuidInterface;

class ColumnDatetime extends ColumnText
{

	public const DEFAULT_FORMAT = DatetimeFactory::DEFAULT_DATETIME_FORMAT;

	public const TEMPLATE_FILE = __DIR__ . '/templates/columnBadge.latte';

	private string $datetimeFormat = self::DEFAULT_FORMAT;

	public function __construct(
		Datagrid $datagrid,
		string $label,
		string $column,
		callable|null $getterMethod = null,
	)
	{
		parent::__construct($datagrid, $label, $column, $getterMethod);
	}

	public function setFormat(string $datetimeFormat): self
	{
		$this->datetimeFormat = $datetimeFormat;

		return $this;
	}

	public function processValue(string|int|float|ImmutableDateTime|UuidInterface|null $value): string
	{
		if ($value instanceof ImmutableDateTime) {
			return $value->format($this->datetimeFormat);
		}

		if ($value === null) {
			return Datagrid::NULLABLE_PLACEHOLDER;
		}

		throw new DatagridColumnException('Invalid column type used');
	}

}
