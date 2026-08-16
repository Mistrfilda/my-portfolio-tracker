<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\InvestmentPlan;

class StockAiInvestmentPlanSchemaFactory
{

	private const array CURRENCIES = ['USD', 'EUR', 'CZK', 'GBP', 'PLN', 'NOK'];

	/**
	 * @param array<string, mixed> $snapshot
	 * @return array<string, mixed>
	 */
	public function createSchema(array $snapshot): array
	{
		$capital = is_array($snapshot['capital'] ?? null) ? $snapshot['capital'] : [];
		$budgetCzk = is_float($capital['requestedAmountCzk'] ?? null)
			|| is_int($capital['requestedAmountCzk'] ?? null)
			? (float) $capital['requestedAmountCzk']
			: 0.0;

		return $this->objectSchema([
			'schemaVersion' => ['type' => 'integer', 'enum' => [1]],
			'planId' => ['type' => 'string', 'format' => 'uuid', 'enum' => [$snapshot['planId'] ?? '']],
			'analysisAsOf' => [
				'type' => 'string',
				'format' => 'date-time',
				'enum' => [$snapshot['analysisAsOf'] ?? ''],
			],
			'decision' => $this->objectSchema([
				'deployment' => $this->enumSchema(['invest_all', 'invest_part', 'hold_cash']),
				'confidence' => $this->enumSchema(['low', 'medium', 'high']),
				'summary' => $this->stringSchema(),
				'deployAmountCzk' => $this->amountSchema($budgetCzk, true),
				'cashReserveCzk' => $this->amountSchema($budgetCzk, true),
				'stagingPlan' => $this->nullableStringSchema(),
			], [
				'deployment', 'confidence', 'summary', 'deployAmountCzk', 'cashReserveCzk', 'stagingPlan',
			]),
			'allocations' => [
				'type' => 'array',
				'maxItems' => 3,
				'items' => $this->createAllocationSchema($budgetCzk),
			],
			'portfolioImpact' => $this->objectSchema([
				'summary' => $this->stringSchema(),
				'diversificationAssessment' => $this->enumSchema(['improves', 'neutral', 'worsens']),
				'concentrationRisksAfter' => $this->stringListSchema(5),
			], ['summary', 'diversificationAssessment', 'concentrationRisksAfter']),
			'alternatives' => [
				'type' => 'array',
				'maxItems' => 3,
				'items' => $this->objectSchema([
					'stockAssetName' => $this->stringSchema(),
					'stockAssetTicker' => $this->stringSchema(),
					'source' => $this->enumSchema(['portfolio', 'watchlist', 'new']),
					'reasonRejected' => $this->stringSchema(),
					'watchCondition' => $this->stringSchema(),
				], ['stockAssetName', 'stockAssetTicker', 'source', 'reasonRejected', 'watchCondition']),
			],
			'keyRisks' => $this->stringListSchema(5),
		], [
			'schemaVersion', 'planId', 'analysisAsOf', 'decision', 'allocations', 'portfolioImpact',
			'alternatives', 'keyRisks',
		]);
	}

	/** @return array<string, mixed> */
	private function createAllocationSchema(float $budgetCzk): array
	{
		return $this->objectSchema([
			'source' => $this->enumSchema(['portfolio', 'watchlist', 'new']),
			'stockAssetId' => ['type' => ['string', 'null'], 'format' => 'uuid'],
			'stockAssetName' => $this->stringSchema(),
			'stockAssetTicker' => $this->stringSchema(),
			'exchange' => $this->nullableStringSchema(),
			'currency' => $this->enumSchema(self::CURRENCIES),
			'allocationAmountCzk' => $this->amountSchema($budgetCzk, false),
			'estimatedWholeShares' => ['type' => ['integer', 'null'], 'minimum' => 1],
			'priceUsed' => ['type' => ['number', 'null'], 'minimum' => 0, 'exclusiveMinimum' => true],
			'maxBuyPrice' => ['type' => ['number', 'null'], 'minimum' => 0, 'exclusiveMinimum' => true],
			'dataQuality' => $this->objectSchema([
				'status' => $this->enumSchema(['sufficient', 'limited', 'insufficient']),
				'issues' => $this->stringListSchema(3),
			], ['status', 'issues']),
			'investmentThesis' => $this->stringSchema(),
			'whyNow' => $this->stringSchema(),
			'dividendSafety' => $this->objectSchema([
				'status' => $this->enumSchema(['strong', 'acceptable', 'watch', 'unsafe', 'unknown']),
				'dividendYieldPercent' => $this->nullableNumberSchema(0),
				'earningsPayoutRatioPercent' => $this->nullableNumberSchema(0),
				'cashFlowPayoutRatioPercent' => $this->nullableNumberSchema(0),
				'dividendHistory' => $this->stringSchema(),
				'debtAssessment' => $this->stringSchema(),
				'sectorAppropriateCoverage' => $this->stringSchema(),
				'summary' => $this->stringSchema(),
			], [
				'status', 'dividendYieldPercent', 'earningsPayoutRatioPercent', 'cashFlowPayoutRatioPercent',
				'dividendHistory', 'debtAssessment', 'sectorAppropriateCoverage', 'summary',
			]),
			'valuation' => $this->objectSchema([
				'assessment' => $this->enumSchema(['undervalued', 'fairly_valued', 'overvalued', 'uncertain']),
				'fairValueLow' => $this->nullableNumberSchema(0, true),
				'fairValueBase' => $this->nullableNumberSchema(0, true),
				'fairValueHigh' => $this->nullableNumberSchema(0, true),
				'currency' => ['type' => ['string', 'null'], 'enum' => [...self::CURRENCIES, null]],
				'method' => $this->nullableStringSchema(),
				'summary' => $this->stringSchema(),
			], [
				'assessment', 'fairValueLow', 'fairValueBase', 'fairValueHigh', 'currency', 'method', 'summary',
			]),
			'keyRisks' => $this->stringListSchema(3),
		], [
			'source', 'stockAssetId', 'stockAssetName', 'stockAssetTicker', 'exchange', 'currency',
			'allocationAmountCzk', 'estimatedWholeShares', 'priceUsed', 'maxBuyPrice', 'dataQuality',
			'investmentThesis', 'whyNow', 'dividendSafety', 'valuation', 'keyRisks',
		]);
	}

	/** @return array<string, mixed> */
	private function amountSchema(float $maximum, bool $allowsZero): array
	{
		$schema = ['type' => 'number', 'minimum' => 0, 'maximum' => $maximum];
		if (!$allowsZero) {
			$schema['exclusiveMinimum'] = true;
		}

		return $schema;
	}

	/** @return array<string, mixed> */
	private function nullableNumberSchema(float $minimum, bool $exclusive = false): array
	{
		$schema = [
			'type' => ['number', 'null'],
			'minimum' => $minimum,
		];
		if ($exclusive) {
			$schema['exclusiveMinimum'] = true;
		}

		return $schema;
	}

	/**
	 * @param array<string, mixed> $properties
	 * @param array<int, string> $required
	 * @return array<string, mixed>
	 */
	private function objectSchema(array $properties, array $required): array
	{
		return [
			'type' => 'object',
			'properties' => $properties,
			'required' => $required,
			'additionalProperties' => false,
		];
	}

	/** @return array<string, mixed> */
	private function stringSchema(): array
	{
		return ['type' => 'string', 'minLength' => 1];
	}

	/** @return array<string, mixed> */
	private function nullableStringSchema(): array
	{
		return ['type' => ['string', 'null']];
	}

	/**
	 * @param array<int, string> $values
	 * @return array<string, mixed>
	 */
	private function enumSchema(array $values): array
	{
		return ['type' => 'string', 'enum' => $values];
	}

	/** @return array<string, mixed> */
	private function stringListSchema(int $maxItems): array
	{
		return [
			'type' => 'array',
			'maxItems' => $maxItems,
			'items' => $this->stringSchema(),
		];
	}

}
