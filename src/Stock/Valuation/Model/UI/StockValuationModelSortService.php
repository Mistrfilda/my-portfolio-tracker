<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\UI;

use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\Model\UI\Control\StockValuationModelTableControlItem;

class StockValuationModelSortService
{

	/**
	 * @param array<StockValuationModelTableControlItem> $items
	 * @return array<StockValuationModelTableControlItem>
	 */
	public function sortItems(
		array $items,
		string|null $sortBy,
		string $sortDirection,
	): array
	{
		if ($sortBy === null) {
			return $items;
		}

		// Sortování podle názvu akcie
		if ($sortBy === 'name') {
			usort(
				$items,
				static function (StockValuationModelTableControlItem $a, StockValuationModelTableControlItem $b) use ($sortDirection) {
					$result = strcasecmp($a->getStockAsset()->getName(), $b->getStockAsset()->getName());
					return $sortDirection === 'desc' ? -$result : $result;
				},
			);
			return $items;
		}

		// Sortování podle tickeru
		if ($sortBy === 'ticker') {
			usort(
				$items,
				static function (StockValuationModelTableControlItem $a, StockValuationModelTableControlItem $b) use ($sortDirection) {
					$result = strcasecmp($a->getStockAsset()->getTicker(), $b->getStockAsset()->getTicker());
					return $sortDirection === 'desc' ? -$result : $result;
				},
			);
			return $items;
		}

		// Sortování podle aktuální ceny
		if ($sortBy === 'current_price') {
			usort(
				$items,
				static function (StockValuationModelTableControlItem $a, StockValuationModelTableControlItem $b) use ($sortDirection) {
					$priceA = $a->getStockAsset()->getAssetCurrentPrice()->getPrice();
					$priceB = $b->getStockAsset()->getAssetCurrentPrice()->getPrice();
					$result = $priceA <=> $priceB;
					return $sortDirection === 'desc' ? -$result : $result;
				},
			);
			return $items;
		}

		// Sortování podle konkrétního modelu (podle indexu)
		// Formát: "model_0", "model_1", atd.
		if (str_starts_with($sortBy, 'model_')) {
			$modelIndex = (int) substr($sortBy, 6);

			usort(
				$items,
				static function (StockValuationModelTableControlItem $a, StockValuationModelTableControlItem $b) use ($modelIndex, $sortDirection) {
					$modelA = $a->getModelResponses()[$modelIndex] ?? null;
					$modelB = $b->getModelResponses()[$modelIndex] ?? null;

					// Pokud model chybí, dej ho na konec
					if ($modelA === null || $modelB === null) {
						return $modelA === null ? 1 : -1;
					}

					$percentageA = $modelA->getCalculatedPercentage();
					$percentageB = $modelB->getCalculatedPercentage();

					// Pokud nelze vypočítat, dej na konec
					if ($percentageA === null || $percentageB === null) {
						if ($percentageA === null && $percentageB === null) {
							return 0;
						}

						return $percentageA === null ? 1 : -1;
					}

					$result = $percentageA <=> $percentageB;
					return $sortDirection === 'desc' ? -$result : $result;
				},
			);
			return $items;
		}

		return $items;
	}

	/**
	 * Vrátí průměrné procento pro akci napříč všemi modely
	 */
	public function getAveragePercentage(StockValuationModelTableControlItem $item): float|null
	{
		$percentages = [];

		foreach ($item->getModelResponses() as $modelResponse) {
			if (
				$modelResponse->getStockValuationModelTrend() !== StockValuationModelState::UNABLE_TO_CALCULATE
				&& $modelResponse->getCalculatedPercentage() !== null
			) {
				$percentages[] = $modelResponse->getCalculatedPercentage();
			}
		}

		if (count($percentages) === 0) {
			return null;
		}

		return array_sum($percentages) / count($percentages);
	}

}
