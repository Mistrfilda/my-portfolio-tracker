<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Filter;

class FilterValue
{

	public function __construct(private string $key, private string|int $value)
	{
	}

	public function getKey(): string
	{
		return $this->key;
	}

	public function getValue(): int|string
	{
		return $this->value;
	}

}
