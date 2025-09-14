<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\UI;

use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;
use ValueError;

class StockValuationSortService
{

	public function __construct(
		private CurrencyConversionFacade $currencyConversionFacade,
	)
	{
	}

	/**
	 * @param array<StockValuation> $stockValuations
	 * @return array<StockValuation>
	 */
	public function sortStockValuations(
		array $stockValuations,
		string $sortBy,
		string $sortDirection = 'asc',
	): array
	{
		if ($sortBy === 'name') {
			return $this->sortByStockName($stockValuations, $sortDirection);
		}

		try {
			$sortByEnum = StockValuationTypeEnum::from($sortBy);
		} catch (ValueError) {
			return $stockValuations;
		}

		return $this->sortByValuationType($stockValuations, $sortByEnum, $sortDirection);
	}

	/**
	 * @param array<StockValuation> $stockValuations
	 * @return array<StockValuation>
	 */
	private function sortByStockName(array $stockValuations, string $sortDirection): array
	{
		usort($stockValuations, static function (StockValuation $a, StockValuation $b) use ($sortDirection): int {
			$result = strcasecmp($a->getStockAsset()->getName(), $b->getStockAsset()->getName());
			return $sortDirection === 'desc' ? -$result : $result;
		});

		return $stockValuations;
	}

	/**
	 * @param array<StockValuation> $stockValuations
	 * @return array<StockValuation>
	 */
	private function sortByValuationType(
		array $stockValuations,
		StockValuationTypeEnum $sortBy,
		string $sortDirection,
	): array
	{
		usort($stockValuations, function (StockValuation $a, StockValuation $b) use ($sortBy, $sortDirection): int {
			$valueA = $this->getComparableValue($a, $sortBy);
			$valueB = $this->getComparableValue($b, $sortBy);

			// Null hodnoty na konec
			if ($valueA === null && $valueB === null) {
				return 0;
			}

			if ($valueA === null) {
				return 1;
			}

			if ($valueB === null) {
				return -1;
			}

			$result = $valueA <=> $valueB;
			return $sortDirection === 'desc' ? -$result : $result;
		});

		return $stockValuations;
	}

	private function getComparableValue(StockValuation $stockValuation, StockValuationTypeEnum $sortBy): float|null
	{
		$valuationData = $stockValuation->getCurrentStockValuationData()[$sortBy->value] ?? null;

		if ($valuationData?->getFloatValue() === null) {
			return null;
		}

		$value = $valuationData->getFloatValue();

		if ($sortBy->isCurrencyValue() && $valuationData->getCurrency() !== CurrencyEnum::CZK) {
			return $this->currencyConversionFacade->convertSimpleValue(
				$value,
				$valuationData->getCurrency(),
				CurrencyEnum::CZK,
			);
		}

		return $value;
	}

}
