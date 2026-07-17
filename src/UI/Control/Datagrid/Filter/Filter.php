<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Filter;

abstract class Filter implements IFilter
{

	/** @var array<string, string|int> */
	private array $values = [];

	public function __construct(
		private string $key,
		private string $label,
		private string $column,
		private string|null $referencedColumn = null,
	)
	{
	}

	public function getKey(): string
	{
		return $this->key;
	}

	public function getLabel(): string
	{
		return $this->label;
	}

	public function getColumn(): string
	{
		return $this->column;
	}

	public function getReferencedColumn(): string|null
	{
		return $this->referencedColumn;
	}

	/**
	 * @return array<string, string|int>
	 */
	public function getValues(): array
	{
		return $this->values;
	}

	public function getValue(string $parameter): string|int|null
	{
		return $this->values[$parameter] ?? null;
	}

	public function setValue(string $parameter, string|int $value): void
	{
		if (in_array($parameter, $this->getParameterKeys(), true) === false) {
			return;
		}

		if ($value === '') {
			unset($this->values[$parameter]);

			return;
		}

		$this->values[$parameter] = $value;
	}

	public function clear(): void
	{
		$this->values = [];
	}

	public function isValueSet(): bool
	{
		return $this->values !== [];
	}

	public function hasParameter(string $parameter): bool
	{
		return in_array($parameter, $this->getParameterKeys(), true);
	}

}
