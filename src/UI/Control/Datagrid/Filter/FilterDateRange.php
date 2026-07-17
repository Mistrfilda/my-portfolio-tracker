<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Filter;

class FilterDateRange extends Filter
{

	public function getType(): string
	{
		return FilterType::FILTER_DATE_RANGE;
	}

	public function getFromParameter(): string
	{
		return $this->getKey() . '_from';
	}

	public function getToParameter(): string
	{
		return $this->getKey() . '_to';
	}

	/**
	 * @return array<string>
	 */
	public function getParameterKeys(): array
	{
		return [
			$this->getFromParameter(),
			$this->getToParameter(),
		];
	}

	public function getActiveValueLabel(): string
	{
		$from = $this->getValue($this->getFromParameter());
		$to = $this->getValue($this->getToParameter());

		if ($from !== null && $to !== null) {
			return sprintf('%s: %s–%s', $this->getLabel(), $from, $to);
		}

		if ($from !== null) {
			return sprintf('%s: od %s', $this->getLabel(), $from);
		}

		return sprintf('%s: do %s', $this->getLabel(), $to);
	}

}
