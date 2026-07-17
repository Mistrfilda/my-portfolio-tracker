<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Filter;

class FilterSelect extends Filter
{

	/**
	 * @param array<string|int, string> $options
	 */
	public function __construct(
		string $key,
		string $label,
		string $column,
		private array $options,
		string|null $referencedColumn = null,
	)
	{
		parent::__construct($key, $label, $column, $referencedColumn);
	}

	public function getType(): string
	{
		return FilterType::FILTER_SELECT;
	}

	/**
	 * @return array<string>
	 */
	public function getParameterKeys(): array
	{
		return [$this->getKey()];
	}

	/**
	 * @return array<string|int, string>
	 */
	public function getOptions(): array
	{
		return $this->options;
	}

	public function getActiveValueLabel(): string
	{
		$value = $this->getValue($this->getKey());
		if ($value === null) {
			return $this->getLabel();
		}

		return sprintf('%s: %s', $this->getLabel(), $this->options[$value] ?? $value);
	}

}
