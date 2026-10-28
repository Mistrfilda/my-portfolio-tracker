<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Data;

use App\Stock\Valuation\StockValuationTypeEnum;

final readonly class StockValuationDataChange
{

	public function __construct(
		public StockValuationTypeEnum $type,
		public StockValuationData|null $previous,
		public StockValuationData|null $current,
	)
	{
	}

	public function getLabel(): string
	{
		if ($this->current?->getFloatValue() === null) {
			return 'Nyní chybí';
		}

		if ($this->previous?->getFloatValue() === null) {
			return 'Nově dostupné';
		}

		return $this->type->isCurrencyValue() && $this->current->getCurrency() !== $this->previous->getCurrency()
			? 'Změna měny'
			: 'Změna hodnoty';
	}

}
