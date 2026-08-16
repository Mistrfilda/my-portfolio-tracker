<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\InvestmentPlan;

use InvalidArgumentException;

class StockAiInvestmentPlanCalculator
{

	public function calculateCurrentPortfolioPercent(float $amountCzk, float $portfolioValueCzk): float
	{
		$this->assertPositiveValues($amountCzk, $portfolioValueCzk);

		return round($amountCzk / $portfolioValueCzk * 100, 4);
	}

	public function calculateProjectedPortfolioPercent(float $amountCzk, float $portfolioValueCzk): float
	{
		$this->assertPositiveValues($amountCzk, $portfolioValueCzk);

		return round($amountCzk / ($portfolioValueCzk + $amountCzk) * 100, 4);
	}

	/**
	 * @param array<string, mixed> $response
	 * @param array<string, mixed> $snapshot
	 * @return array<string, mixed>
	 */
	public function enrichResponse(array $response, array $snapshot): array
	{
		$capital = $this->normalizeObject($snapshot['capital'] ?? null);
		$budgetCzk = $this->getNumericValue($capital, 'requestedAmountCzk');
		$portfolioValueCzk = $this->getNumericValue($capital, 'currentPortfolioValueCzk');
		$decision = $this->normalizeObject($response['decision'] ?? null);
		$deployAmountCzk = $this->getNumericValue($decision, 'deployAmountCzk');
		$projectedPortfolioValueCzk = $portfolioValueCzk + $deployAmountCzk;

		$response['calculated'] = [
			'deployedPercentOfBudget' => $budgetCzk > 0
				? round($deployAmountCzk / $budgetCzk * 100, 2)
				: 0.0,
			'projectedStockPortfolioValueCzk' => round($projectedPortfolioValueCzk, 2),
		];

		$portfolioItems = is_array($snapshot['portfolio'] ?? null) ? $snapshot['portfolio'] : [];
		$allocations = is_array($response['allocations'] ?? null) ? $response['allocations'] : [];
		foreach ($allocations as $index => $allocation) {
			$allocation = $this->normalizeObject($allocation);
			if ($allocation === []) {
				continue;
			}

			$allocationAmountCzk = $this->getNumericValue($allocation, 'allocationAmountCzk');
			$beforePercent = $this->findCurrentPortfolioPercent($allocation, $portfolioItems);
			$currentPositionValueCzk = $portfolioValueCzk * $beforePercent / 100;
			$allocation['calculated'] = [
				'allocationPercentOfBudget' => $budgetCzk > 0
					? round($allocationAmountCzk / $budgetCzk * 100, 2)
					: 0.0,
				'beforePortfolioPercent' => round($beforePercent, 2),
				'projectedPortfolioPercent' => $projectedPortfolioValueCzk > 0
					? round(($currentPositionValueCzk + $allocationAmountCzk) / $projectedPortfolioValueCzk * 100, 2)
					: 0.0,
			];
			$allocations[$index] = $allocation;
		}

		$response['allocations'] = array_values($allocations);

		return $response;
	}

	private function assertPositiveValues(float $amountCzk, float $portfolioValueCzk): void
	{
		if (!is_finite($amountCzk) || $amountCzk <= 0) {
			throw new InvalidArgumentException('Investment amount must be greater than zero.');
		}

		if (!is_finite($portfolioValueCzk) || $portfolioValueCzk <= 0) {
			throw new InvalidArgumentException('Current stock portfolio value must be greater than zero.');
		}
	}

	/** @param array<string, mixed> $data */
	private function getNumericValue(array $data, string $key): float
	{
		$value = $data[$key] ?? null;

		return is_float($value) || is_int($value) ? (float) $value : 0.0;
	}

	/**
	 * @param array<string, mixed> $allocation
	 * @param array<mixed> $portfolioItems
	 */
	private function findCurrentPortfolioPercent(array $allocation, array $portfolioItems): float
	{
		if (($allocation['source'] ?? null) !== 'portfolio') {
			return 0.0;
		}

		$stockAssetId = $allocation['stockAssetId'] ?? null;
		foreach ($portfolioItems as $item) {
			$item = $this->normalizeObject($item);
			if (($item['stockAssetId'] ?? null) !== $stockAssetId) {
				continue;
			}

			return $this->getNumericValue($item, 'portfolioPercentage');
		}

		return 0.0;
	}

	/** @return array<string, mixed> */
	private function normalizeObject(mixed $value): array
	{
		if (!is_array($value)) {
			return [];
		}

		$result = [];
		foreach ($value as $key => $item) {
			if (is_string($key)) {
				$result[$key] = $item;
			}
		}

		return $result;
	}

}
