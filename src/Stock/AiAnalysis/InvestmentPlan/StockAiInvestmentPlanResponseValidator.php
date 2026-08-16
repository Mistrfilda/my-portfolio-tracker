<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\InvestmentPlan;

use App\Utils\TypeValidator;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use Nette\Utils\Json;
use Nette\Utils\JsonException;

class StockAiInvestmentPlanResponseValidator
{

	private const float MONEY_TOLERANCE = 1.0;

	public function __construct(private readonly StockAiInvestmentPlanSchemaFactory $schemaFactory)
	{
	}

	/** @param array<string, mixed> $snapshot */
	public function validate(string $rawResponse, array $snapshot): StockAiInvestmentPlanResponse
	{
		try {
			$data = Json::decode($rawResponse, forceArrays: true);
		} catch (JsonException $exception) {
			throw new StockAiInvestmentPlanValidationException([
				sprintf('Response is not valid JSON: %s', $exception->getMessage()),
			]);
		}

		if (!is_array($data) || array_is_list($data)) {
			throw new StockAiInvestmentPlanValidationException(['Response must contain a JSON object.']);
		}

		$data = $this->normalizeObject($data);
		$errors = $this->validateAgainstSchema($data, $this->schemaFactory->createSchema($snapshot));
		if ($errors === []) {
			$errors = $this->validateBusinessRules($data, $snapshot);
		}

		if ($errors !== []) {
			throw new StockAiInvestmentPlanValidationException($errors);
		}

		return new StockAiInvestmentPlanResponse(
			TypeValidator::validateInt($data['schemaVersion'] ?? null),
			TypeValidator::validateString($data['planId'] ?? null),
			TypeValidator::validateString($data['analysisAsOf'] ?? null),
			$this->normalizeObject($this->getArray($data, 'decision')),
			$this->normalizeObjectList($this->getArray($data, 'allocations')),
			$this->normalizeObject($this->getArray($data, 'portfolioImpact')),
			$this->normalizeObjectList($this->getArray($data, 'alternatives')),
			$this->normalizeStringList($this->getArray($data, 'keyRisks')),
		);
	}

	/**
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $schema
	 * @return array<int, string>
	 */
	private function validateAgainstSchema(array $data, array $schema): array
	{
		$validator = new Validator();
		$dataObject = Json::decode(Json::encode($data));
		$schemaObject = Json::decode(Json::encode($schema));
		$validator->validate(
			$dataObject,
			$schemaObject,
			Constraint::CHECK_MODE_NORMAL,
		);

		$errors = [];
		foreach ($validator->getErrors() as $error) {
			if (!is_array($error)) {
				$errors[] = 'Schema validation failed.';
				continue;
			}

			$property = is_string($error['property'] ?? null) && $error['property'] !== ''
				? $error['property'] . ': '
				: '';
			$message = is_string($error['message'] ?? null) ? $error['message'] : 'Schema validation failed.';
			$errors[] = $property . $message;
		}

		return array_values(array_unique($errors));
	}

	/**
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $snapshot
	 * @return array<int, string>
	 */
	private function validateBusinessRules(array $data, array $snapshot): array
	{
		$errors = [];
		$capital = $this->normalizeObject($this->getArray($snapshot, 'capital'));
		$decision = $this->normalizeObject($this->getArray($data, 'decision'));
		$budgetCzk = $this->getNumber($capital, 'requestedAmountCzk');
		$deployAmountCzk = $this->getNumber($decision, 'deployAmountCzk');
		$cashReserveCzk = $this->getNumber($decision, 'cashReserveCzk');
		$deployment = is_string($decision['deployment'] ?? null) ? $decision['deployment'] : '';
		$allocations = $this->getArray($data, 'allocations');

		if (!$this->moneyMatches($deployAmountCzk + $cashReserveCzk, $budgetCzk)) {
			$errors[] = 'decision.deployAmountCzk plus decision.cashReserveCzk must equal the immutable CZK budget.';
		}

		$allocationTotal = 0.0;
		$seenTickers = [];
		foreach ($allocations as $index => $allocationValue) {
			if (!is_array($allocationValue)) {
				continue;
			}

			$allocation = $this->normalizeObject($allocationValue);
			$allocationTotal += $this->getNumber($allocation, 'allocationAmountCzk');
			$ticker = strtoupper(is_string($allocation['stockAssetTicker'] ?? null)
				? trim($allocation['stockAssetTicker'])
				: '');
			$path = sprintf('allocations[%d]', $index);
			if (isset($seenTickers[$ticker])) {
				$errors[] = sprintf('%s duplicates ticker %s.', $path, $ticker);
			} else {
				$seenTickers[$ticker] = true;
			}

			$this->validateAllocationIdentity($allocation, $snapshot, $path, $errors);
			$this->validateAllocationQuality($allocation, $path, $errors);
		}

		if (!$this->moneyMatches($allocationTotal, $deployAmountCzk)) {
			$errors[] = 'The allocation total must equal decision.deployAmountCzk.';
		}

		if ($deployment === 'hold_cash' && ($deployAmountCzk > self::MONEY_TOLERANCE || $allocations !== [])) {
			$errors[] = 'hold_cash requires a zero deployment and no allocations.';
		}

		if ($deployment === 'invest_all' && ($deployAmountCzk <= 0 || $cashReserveCzk > self::MONEY_TOLERANCE)) {
			$errors[] = 'invest_all requires a positive deployment and no material cash reserve.';
		}

		if (
			$deployment === 'invest_part'
			&& ($deployAmountCzk <= self::MONEY_TOLERANCE || $cashReserveCzk <= self::MONEY_TOLERANCE)
		) {
			$errors[] = 'invest_part requires both a positive deployment and a positive cash reserve.';
		}

		return array_values(array_unique($errors));
	}

	/**
	 * @param array<string, mixed> $allocation
	 * @param array<string, mixed> $snapshot
	 * @param array<int, string> $errors
	 */
	private function validateAllocationIdentity(
		array $allocation,
		array $snapshot,
		string $path,
		array &$errors,
	): void
	{
		$source = is_string($allocation['source'] ?? null) ? $allocation['source'] : '';
		$ticker = strtoupper(is_string($allocation['stockAssetTicker'] ?? null)
			? trim($allocation['stockAssetTicker'])
			: '');
		$portfolio = $this->createIdentityMaps($this->getArray($snapshot, 'portfolio'));
		$watchlist = $this->createIdentityMaps($this->getArray($snapshot, 'watchlist'));

		if ($source === 'new') {
			if (($allocation['stockAssetId'] ?? null) !== null) {
				$errors[] = sprintf('%s.stockAssetId must be null for a newly researched company.', $path);
			}

			if (isset($portfolio['byTicker'][$ticker]) || isset($watchlist['byTicker'][$ticker])) {
				$errors[] = sprintf('%s source cannot be new because the ticker exists in the snapshot.', $path);
			}

			return;
		}

		$id = is_string($allocation['stockAssetId'] ?? null) ? $allocation['stockAssetId'] : '';
		$map = $source === 'portfolio' ? $portfolio : $watchlist;
		$expected = $map['byId'][$id] ?? null;
		if (!is_array($expected)) {
			$errors[] = sprintf('%s contains an unexpected stockAssetId for source %s.', $path, $source);
			return;
		}

		if ($source === 'watchlist' && isset($portfolio['byTicker'][$ticker])) {
			$errors[] = sprintf('%s must use source portfolio because this ticker is already held.', $path);
		}

		foreach (['stockAssetId', 'stockAssetName', 'stockAssetTicker', 'currency'] as $key) {
			if (($allocation[$key] ?? null) !== ($expected[$key] ?? null)) {
				$errors[] = sprintf('%s.%s must match the immutable input snapshot.', $path, $key);
			}
		}
	}

	/**
	 * @param array<string, mixed> $allocation
	 * @param array<int, string> $errors
	 */
	private function validateAllocationQuality(array $allocation, string $path, array &$errors): void
	{
		$dataQuality = $this->normalizeObject($this->getArray($allocation, 'dataQuality'));
		if (($dataQuality['status'] ?? null) === 'insufficient') {
			$errors[] = sprintf('%s cannot allocate capital when dataQuality.status is insufficient.', $path);
		}

		$dividendSafety = $this->normalizeObject($this->getArray($allocation, 'dividendSafety'));
		if (!in_array($dividendSafety['status'] ?? null, ['strong', 'acceptable'], true)) {
			$errors[] = sprintf('%s requires strong or acceptable dividend safety.', $path);
		}

		$valuation = $this->normalizeObject($this->getArray($allocation, 'valuation'));
		$values = [
			$valuation['fairValueLow'] ?? null,
			$valuation['fairValueBase'] ?? null,
			$valuation['fairValueHigh'] ?? null,
			$valuation['currency'] ?? null,
			$valuation['method'] ?? null,
		];
		$nonNullCount = count(array_filter($values, static fn (mixed $value): bool => $value !== null));
		if ($nonNullCount !== 0 && $nonNullCount !== count($values)) {
			$errors[] = sprintf(
				'%s.valuation price range, currency, and method must be all null or all present.',
				$path,
			);
			return;
		}

		if ($nonNullCount === 0) {
			if (($valuation['assessment'] ?? null) !== 'uncertain') {
				$errors[] = sprintf('%s.valuation.assessment must be uncertain without a fair value range.', $path);
			}

			return;
		}

		$low = is_float($values[0]) || is_int($values[0]) ? (float) $values[0] : 0.0;
		$base = is_float($values[1]) || is_int($values[1]) ? (float) $values[1] : 0.0;
		$high = is_float($values[2]) || is_int($values[2]) ? (float) $values[2] : 0.0;
		if ($low <= 0 || $low > $base || $base > $high) {
			$errors[] = sprintf('%s.valuation must satisfy 0 < low <= base <= high.', $path);
		}

		if ($values[3] !== ($allocation['currency'] ?? null)) {
			$errors[] = sprintf('%s.valuation.currency must equal the allocation currency.', $path);
		}
	}

	/**
	 * @param array<mixed> $items
	 * @return array{byId: array<string, array<string, mixed>>, byTicker: array<string, array<string, mixed>>}
	 */
	private function createIdentityMaps(array $items): array
	{
		$byId = [];
		$byTicker = [];
		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}

			$item = $this->normalizeObject($item);
			$id = is_string($item['stockAssetId'] ?? null) ? $item['stockAssetId'] : null;
			$ticker = is_string($item['stockAssetTicker'] ?? null)
				? strtoupper(trim($item['stockAssetTicker']))
				: null;
			if ($id !== null) {
				$byId[$id] = $item;
			}

			if ($ticker !== null) {
				$byTicker[$ticker] = $item;
			}
		}

		return ['byId' => $byId, 'byTicker' => $byTicker];
	}

	private function moneyMatches(float $actual, float $expected): bool
	{
		return abs($actual - $expected) <= self::MONEY_TOLERANCE;
	}

	/** @param array<string, mixed> $data */
	private function getNumber(array $data, string $key): float
	{
		$value = $data[$key] ?? null;

		return is_float($value) || is_int($value) ? (float) $value : 0.0;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<mixed>
	 */
	private function getArray(array $data, string $key): array
	{
		$value = $data[$key] ?? null;

		return is_array($value) ? $value : [];
	}

	/**
	 * @param array<mixed> $data
	 * @return array<string, mixed>
	 */
	private function normalizeObject(array $data): array
	{
		$result = [];
		foreach ($data as $key => $value) {
			if (!is_string($key)) {
				throw new StockAiInvestmentPlanValidationException(['Expected a JSON object with string keys.']);
			}

			$result[$key] = $value;
		}

		return $result;
	}

	/**
	 * @param array<mixed> $items
	 * @return list<array<string, mixed>>
	 */
	private function normalizeObjectList(array $items): array
	{
		$result = [];
		foreach ($items as $item) {
			if (is_array($item)) {
				$result[] = $this->normalizeObject($item);
			}
		}

		return $result;
	}

	/**
	 * @param array<mixed> $items
	 * @return list<string>
	 */
	private function normalizeStringList(array $items): array
	{
		return array_values(array_filter($items, static fn (mixed $item): bool => is_string($item)));
	}

}
