<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model;

class StockValuationModelUsedValue
{

	public function __construct(
		private string $label,
		private int|string|float $value,
	)
	{
	}

	public function getLabel(): string
	{
		return $this->label;
	}

	public function getValue(): float|int|string
	{
		return $this->value;
	}

}
