<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\V2;

use App\Stock\AiAnalysis\StockAiAnalysisPortfolioPromptTypeEnum;
use InvalidArgumentException;

class StockAiAnalysisV2SchemaFactory
{

	private const array CURRENCIES = ['USD', 'EUR', 'CZK', 'GBP', 'PLN', 'NOK'];

	/**
	 * @param array<string, mixed> $snapshot
	 * @return array<string, mixed>
	 */
	public function createFullSchema(array $snapshot): array
	{
		$scope = $this->getScope($snapshot);
		$properties = $this->createMetadataProperties($snapshot);
		$required = ['schemaVersion', 'runId', 'analysisAsOf'];

		if ($scope['includesPortfolio']) {
			$count = count(is_array($snapshot['portfolio'] ?? null) ? $snapshot['portfolio'] : []);
			$properties['portfolioAnalysis'] = $this->createAnalysisListSchema(
				$this->createCompanyAnalysisSchema(['hold', 'consider_selling', 'add_more', 'watch_closely']),
				$count,
			);
			$required[] = 'portfolioAnalysis';
		}

		if ($scope['includesWatchlist']) {
			$count = count(is_array($snapshot['watchlist'] ?? null) ? $snapshot['watchlist'] : []);
			$properties['watchlistAnalysis'] = $this->createAnalysisListSchema(
				$this->createCompanyAnalysisSchema(['consider_buying', 'wait', 'not_interesting']),
				$count,
			);
			$required[] = 'watchlistAnalysis';
		}

		if ($scope['includesStockAnalysis']) {
			$properties['stockAnalysis'] = $this->createSingleStockAnalysisSchema();
			$required[] = 'stockAnalysis';
		}

		if ($scope['includesMarketOverview']) {
			$properties['marketOverview'] = $this->createMarketOverviewSchema();
			$required[] = 'marketOverview';
		}

		if (
			$scope['includesPortfolio']
			&& $scope['portfolioPromptType'] !== StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF->value
		) {
			$properties['portfolioEvaluation'] = $this->createPortfolioEvaluationSchema();
			$required[] = 'portfolioEvaluation';
		}

		if (
			($scope['includesPortfolio'] || $scope['includesWatchlist'])
			&& $scope['portfolioPromptType'] === StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF->value
		) {
			$properties['dailyBrief'] = $this->createDailyBriefSchema();
			$required[] = 'dailyBrief';
		}

		return $this->objectSchema($properties, $required);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function createCompanyResultSchema(): array
	{
		return [
			'type' => 'object',
			'properties' => [
				'portfolioAnalysis' => $this->createAnalysisListSchema(
					$this->createCompanyAnalysisSchema(['hold', 'consider_selling', 'add_more', 'watch_closely']),
					1,
				),
				'watchlistAnalysis' => $this->createAnalysisListSchema(
					$this->createCompanyAnalysisSchema(['consider_buying', 'wait', 'not_interesting']),
					1,
				),
				'stockAnalysis' => $this->createSingleStockAnalysisSchema(),
			],
			'minProperties' => 1,
			'maxProperties' => 1,
			'additionalProperties' => false,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function createCompanySchema(string $rootKey): array
	{
		$property = match ($rootKey) {
			'portfolioAnalysis' => $this->createAnalysisListSchema(
				$this->createCompanyAnalysisSchema(['hold', 'consider_selling', 'add_more', 'watch_closely']),
				1,
			),
			'watchlistAnalysis' => $this->createAnalysisListSchema(
				$this->createCompanyAnalysisSchema(['consider_buying', 'wait', 'not_interesting']),
				1,
			),
			'stockAnalysis' => $this->createSingleStockAnalysisSchema(),
			default => throw new InvalidArgumentException(sprintf('Unsupported analysis root key "%s".', $rootKey)),
		};

		return $this->objectSchema([$rootKey => $property], [$rootKey]);
	}

	/**
	 * @param array<string, mixed> $schema
	 * @return array<string, mixed>
	 */
	public function toGeminiResponseSchema(array $schema): array
	{
		return $this->convertToGeminiSchema($schema);
	}

	/**
	 * @param array<string, mixed> $snapshot
	 * @return array<string, mixed>
	 */
	public function createReduceSchema(array $snapshot): array
	{
		$scope = $this->getScope($snapshot);
		$properties = [];
		$required = [];

		if ($scope['includesMarketOverview']) {
			$properties['marketOverview'] = $this->createMarketOverviewSchema();
			$required[] = 'marketOverview';
		}

		if (
			$scope['includesPortfolio']
			&& $scope['portfolioPromptType'] !== StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF->value
		) {
			$properties['portfolioEvaluation'] = $this->createPortfolioEvaluationSchema();
			$required[] = 'portfolioEvaluation';
		}

		if (
			($scope['includesPortfolio'] || $scope['includesWatchlist'])
			&& $scope['portfolioPromptType'] === StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF->value
		) {
			$properties['dailyBrief'] = $this->createDailyBriefSchema();
			$required[] = 'dailyBrief';
		}

		return $this->objectSchema($properties, $required);
	}

	/**
	 * @param array<string, mixed> $snapshot
	 * @return array<string, mixed>
	 */
	private function createMetadataProperties(array $snapshot): array
	{
		return [
			'schemaVersion' => ['type' => 'integer', 'enum' => [2]],
			'runId' => ['type' => 'string', 'enum' => [$snapshot['runId'] ?? '']],
			'analysisAsOf' => [
				'type' => 'string',
				'format' => 'date-time',
				'enum' => [$snapshot['analysisAsOf'] ?? ''],
			],
		];
	}

	/**
	 * @param array<int, string> $actions
	 * @return array<string, mixed>
	 */
	private function createCompanyAnalysisSchema(array $actions): array
	{
		$properties = $this->createCommonAnalysisProperties($actions, false);
		$properties['performanceComment'] = $this->stringSchema();

		return $this->objectSchema($properties, array_keys($properties));
	}

	/**
	 * @return array<string, mixed>
	 */
	private function createSingleStockAnalysisSchema(): array
	{
		$properties = $this->createCommonAnalysisProperties(
			['consider_buying', 'hold', 'consider_selling'],
			true,
		);
		$properties['businessSummary'] = $this->stringSchema();
		$properties['moatAnalysis'] = $this->stringSchema();
		$properties['financialHealth'] = $this->stringSchema();
		$properties['conclusion'] = $this->stringSchema();

		return $this->objectSchema($properties, array_keys($properties));
	}

	/**
	 * @param array<int, string> $actions
	 * @return array<string, mixed>
	 */
	private function createCommonAnalysisProperties(array $actions, bool $nullableId): array
	{
		return [
			'stockAssetId' => [
				'type' => $nullableId ? ['string', 'null'] : 'string',
				'format' => 'uuid',
			],
			'stockAssetName' => $this->stringSchema(),
			'stockAssetTicker' => $this->stringSchema(),
			'summary' => $this->stringSchema(),
			'dataQuality' => $this->objectSchema([
				'status' => $this->enumSchema(['sufficient', 'limited', 'insufficient']),
				'issues' => $this->stringListSchema(3),
			], ['status', 'issues']),
			'materialEvents' => [
				'type' => 'array',
				'maxItems' => 5,
				'items' => $this->objectSchema([
					'date' => ['type' => ['string', 'null'], 'format' => 'date'],
					'category' => $this->enumSchema([
						'earnings', 'guidance', 'product', 'management', 'capital_allocation',
						'regulation', 'legal', 'macro', 'geopolitics', 'other',
					]),
					'sentiment' => $this->enumSchema(['positive', 'negative', 'neutral', 'mixed']),
					'headline' => $this->stringSchema(),
					'impact' => $this->stringSchema(),
					'timeHorizon' => $this->enumSchema(['days', 'months', 'years']),
				], ['date', 'category', 'sentiment', 'headline', 'impact', 'timeHorizon']),
			],
			'earnings' => $this->objectSchema([
				'latestPeriod' => ['type' => ['string', 'null']],
				'resultVsExpectations' => $this->enumSchema(['beat', 'met', 'missed', 'mixed', 'unknown']),
				'nextEarningsDate' => ['type' => ['string', 'null'], 'format' => 'date'],
				'summary' => $this->stringSchema(),
			], ['latestPeriod', 'resultVsExpectations', 'nextEarningsDate', 'summary']),
			'dividend' => $this->objectSchema([
				'status' => $this->enumSchema(['not_paid', 'stable', 'growing', 'at_risk', 'unknown']),
				'summary' => $this->stringSchema(),
			], ['status', 'summary']),
			'catalysts' => [
				'type' => 'array',
				'maxItems' => 3,
				'items' => $this->objectSchema([
					'title' => $this->stringSchema(),
					'horizon' => $this->enumSchema(['near_term', 'medium_term', 'long_term']),
					'rationale' => $this->stringSchema(),
				], ['title', 'horizon', 'rationale']),
			],
			'risks' => [
				'type' => 'array',
				'maxItems' => 3,
				'items' => $this->objectSchema([
					'title' => $this->stringSchema(),
					'likelihood' => $this->enumSchema(['low', 'medium', 'high', 'unknown']),
					'impact' => $this->enumSchema(['low', 'medium', 'high']),
					'rationale' => $this->stringSchema(),
				], ['title', 'likelihood', 'impact', 'rationale']),
			],
			'valuation' => $this->objectSchema([
				'assessment' => $this->enumSchema(['undervalued', 'fairly_valued', 'overvalued', 'uncertain']),
				'fairValueLow' => ['type' => ['number', 'null']],
				'fairValueBase' => ['type' => ['number', 'null']],
				'fairValueHigh' => ['type' => ['number', 'null']],
				'currency' => ['type' => ['string', 'null'], 'enum' => [...self::CURRENCIES, null]],
				'method' => ['type' => ['string', 'null']],
				'summary' => $this->stringSchema(),
			], [
				'assessment', 'fairValueLow', 'fairValueBase', 'fairValueHigh', 'currency', 'method', 'summary',
			]),
			'recommendation' => $this->objectSchema([
				'action' => $this->enumSchema($actions),
				'confidence' => $this->enumSchema(['low', 'medium', 'high']),
				'reasoning' => $this->stringSchema(),
				'watchConditions' => $this->stringListSchema(3),
			], ['action', 'confidence', 'reasoning', 'watchConditions']),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function createMarketOverviewSchema(): array
	{
		return $this->objectSchema([
			'summary' => $this->stringSchema(),
			'sentiment' => $this->enumSchema(['bullish', 'bearish', 'neutral']),
			'keyDrivers' => [
				'type' => 'array',
				'maxItems' => 5,
				'items' => $this->objectSchema([
					'title' => $this->stringSchema(),
					'direction' => $this->enumSchema(['positive', 'negative', 'neutral', 'mixed']),
					'impact' => $this->stringSchema(),
				], ['title', 'direction', 'impact']),
			],
			'upcomingEvents' => [
				'type' => 'array',
				'maxItems' => 5,
				'items' => $this->objectSchema([
					'date' => ['type' => ['string', 'null'], 'format' => 'date'],
					'title' => $this->stringSchema(),
					'relevance' => $this->stringSchema(),
				], ['date', 'title', 'relevance']),
			],
			'geopoliticalRisks' => [
				'type' => 'array',
				'maxItems' => 3,
				'items' => $this->objectSchema([
					'title' => $this->stringSchema(),
					'affectedAreas' => $this->stringListSchema(5),
					'impact' => $this->stringSchema(),
				], ['title', 'affectedAreas', 'impact']),
			],
		], ['summary', 'sentiment', 'keyDrivers', 'upcomingEvents', 'geopoliticalRisks']);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function createPortfolioEvaluationSchema(): array
	{
		return $this->objectSchema([
			'summary' => $this->stringSchema(),
			'performance7DaysSummary' => $this->stringSchema(),
			'concentrationRisks' => [
				'type' => 'array',
				'maxItems' => 5,
				'items' => $this->objectSchema([
					'sector' => $this->stringSchema(),
					'allocationPercent' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
					'assessment' => $this->stringSchema(),
				], ['sector', 'allocationPercent', 'assessment']),
			],
			'actions' => $this->priorityActionListSchema(),
		], ['summary', 'performance7DaysSummary', 'concentrationRisks', 'actions']);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function createDailyBriefSchema(): array
	{
		return $this->objectSchema([
			'summary' => $this->stringSchema(),
			'marketPulse' => $this->stringSchema(),
			'portfolioImpactSummary' => ['type' => ['string', 'null']],
			'watchlistSummary' => ['type' => ['string', 'null']],
			'alerts' => [
				'type' => 'array',
				'maxItems' => 5,
				'items' => $this->objectSchema([
					'severity' => $this->enumSchema(['info', 'warning', 'critical']),
					'title' => $this->stringSchema(),
					'detail' => $this->stringSchema(),
					'horizon' => $this->enumSchema(['today', 'days', 'weeks']),
				], ['severity', 'title', 'detail', 'horizon']),
			],
			'checklist' => [
				'type' => 'array',
				'maxItems' => 5,
				'items' => $this->objectSchema([
					'priority' => $this->enumSchema(['low', 'medium', 'high']),
					'action' => $this->stringSchema(),
					'reason' => $this->stringSchema(),
				], ['priority', 'action', 'reason']),
			],
			'actionNeeded' => $this->enumSchema(['none', 'monitor', 'review_positions', 'review_watchlist']),
		], [
			'summary', 'marketPulse', 'portfolioImpactSummary', 'watchlistSummary', 'alerts', 'checklist',
			'actionNeeded',
		]);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function priorityActionListSchema(): array
	{
		return [
			'type' => 'array',
			'maxItems' => 5,
			'items' => $this->objectSchema([
				'priority' => $this->enumSchema(['low', 'medium', 'high']),
				'action' => $this->stringSchema(),
				'rationale' => $this->stringSchema(),
			], ['priority', 'action', 'rationale']),
		];
	}

	/**
	 * @param array<string, mixed> $itemSchema
	 * @return array<string, mixed>
	 */
	private function createAnalysisListSchema(array $itemSchema, int $count): array
	{
		return [
			'type' => 'array',
			'minItems' => $count,
			'maxItems' => $count,
			'items' => $itemSchema,
		];
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

	/**
	 * @return array<string, mixed>
	 */
	private function stringSchema(): array
	{
		return ['type' => 'string', 'minLength' => 1];
	}

	/**
	 * @param array<int, string> $values
	 * @return array<string, mixed>
	 */
	private function enumSchema(array $values): array
	{
		return ['type' => 'string', 'enum' => $values];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function stringListSchema(int $maxItems): array
	{
		return [
			'type' => 'array',
			'maxItems' => $maxItems,
			'items' => $this->stringSchema(),
		];
	}

	/**
	 * @param array<string, mixed> $snapshot
	 * @return array{
	 *     includesPortfolio: bool,
	 *     includesWatchlist: bool,
	 *     includesMarketOverview: bool,
	 *     includesStockAnalysis: bool,
	 *     portfolioPromptType: string|null
	 * }
	 */
	private function getScope(array $snapshot): array
	{
		$scope = is_array($snapshot['scope'] ?? null) ? $snapshot['scope'] : [];

		return [
			'includesPortfolio' => ($scope['includesPortfolio'] ?? false) === true,
			'includesWatchlist' => ($scope['includesWatchlist'] ?? false) === true,
			'includesMarketOverview' => ($scope['includesMarketOverview'] ?? false) === true,
			'includesStockAnalysis' => ($scope['includesStockAnalysis'] ?? false) === true,
			'portfolioPromptType' => is_string($scope['portfolioPromptType'] ?? null)
				? $scope['portfolioPromptType']
				: null,
		];
	}

	/**
	 * @param array<string, mixed> $schema
	 * @return array<string, mixed>
	 */
	private function convertToGeminiSchema(array $schema): array
	{
		$result = [];
		foreach ($schema as $key => $value) {
			if (
				$key === 'additionalProperties'
				|| $key === 'minLength'
				|| $key === 'minProperties'
				|| $key === 'maxProperties'
			) {
				continue;
			}

			if ($key === 'type') {
				if (is_array($value)) {
					$nonNullTypes = array_values(
						array_filter($value, static fn (mixed $type): bool => $type !== 'null'),
					);
					$type = is_string($nonNullTypes[0] ?? null) ? $nonNullTypes[0] : 'string';
					$result['type'] = strtoupper($type);
					$result['nullable'] = in_array('null', $value, true);
				} elseif (is_string($value)) {
					$result['type'] = strtoupper($value);
				}

				continue;
			}

			if ($key === 'format' && $value === 'uuid') {
				continue;
			}

			if ($key === 'enum' && is_array($value)) {
				$result[$key] = array_values(array_filter($value, static fn (mixed $item): bool => $item !== null));
				continue;
			}

			if ($key === 'properties' && is_array($value)) {
				$properties = [];
				foreach ($value as $propertyName => $propertySchema) {
					if (is_string($propertyName) && is_array($propertySchema)) {
						$properties[$propertyName] = $this->convertToGeminiSchema(
							$this->normalizeSchemaObject($propertySchema),
						);
					}
				}

				$result[$key] = $properties;
				continue;
			}

			if ($key === 'items' && is_array($value)) {
				$result[$key] = $this->convertToGeminiSchema($this->normalizeSchemaObject($value));
				continue;
			}

			$result[$key] = $value;
		}

		return $result;
	}

	/**
	 * @param array<mixed> $schema
	 * @return array<string, mixed>
	 */
	private function normalizeSchemaObject(array $schema): array
	{
		$result = [];
		foreach ($schema as $key => $value) {
			if (is_string($key)) {
				$result[$key] = $value;
			}
		}

		return $result;
	}

}
