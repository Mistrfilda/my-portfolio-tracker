<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Filter;

class FilterText extends Filter
{

	public function getType(): string
	{
		return FilterType::FILTER_TEXT;
	}

	/**
	 * @return array<string>
	 */
	public function getParameterKeys(): array
	{
		return [$this->getKey()];
	}

	public function getActiveValueLabel(): string
	{
		return sprintf('%s: %s', $this->getLabel(), $this->getValue($this->getKey()));
	}

}
