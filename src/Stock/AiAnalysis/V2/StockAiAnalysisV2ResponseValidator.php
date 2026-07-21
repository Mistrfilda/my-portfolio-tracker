<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\V2;

use App\Utils\TypeValidator;
use CuyZ\Valinor\Mapper\Source\Source;
use CuyZ\Valinor\MapperBuilder;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Throwable;

class StockAiAnalysisV2ResponseValidator
{

	public function __construct(private readonly StockAiAnalysisV2SchemaFactory $schemaFactory)
	{
	}

	/**
	 * @param array<string, mixed> $snapshot
	 */
	public function validate(string $rawResponse, array $snapshot): StockAiAnalysisV2Response
	{
		try {
			$data = Json::decode($rawResponse, forceArrays: true);
		} catch (JsonException $exception) {
			throw new StockAiAnalysisV2ValidationException([
				sprintf('Response is not valid JSON: %s', $exception->getMessage()),
			]);
		}

		if (!is_array($data) || array_is_list($data)) {
			throw new StockAiAnalysisV2ValidationException(['Response must contain a JSON object.']);
		}

		$data = $this->normalizeObject($data);

		$errors = $this->validateArrayAgainstSchema($data, $this->schemaFactory->createFullSchema($snapshot));
		if ($errors === []) {
			$errors = $this->validateBusinessRules($data, $snapshot);
		}

		if ($errors !== []) {
			throw new StockAiAnalysisV2ValidationException($errors);
		}

		try {
			return new MapperBuilder()
				->mapper()
				->map(StockAiAnalysisV2Response::class, Source::array($data));
		} catch (Throwable $exception) {
			throw new StockAiAnalysisV2ValidationException([
				sprintf('Response could not be mapped to the v2 contract: %s', $exception->getMessage()),
			]);
		}
	}

	/**
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $schema
	 * @return array<int, string>
	 */
	public function validateArrayAgainstSchema(array $data, array $schema): array
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
	 * @param array<string, mixed> $schema
	 * @param array<string, mixed>|null $expectedItem
	 * @return array<int, string>
	 */
	public function validatePartial(
		array $data,
		array $schema,
		string|null $rootKey = null,
		array|null $expectedItem = null,
	): array
	{
		$errors = $this->validateArrayAgainstSchema($data, $schema);
		if ($errors !== [] || $rootKey === null || $expectedItem === null) {
			return $errors;
		}

		$analysis = $rootKey === 'stockAnalysis'
			? $data[$rootKey] ?? null
			: (is_array($data[$rootKey] ?? null) ? ($data[$rootKey][0] ?? null) : null);
		if (!is_array($analysis)) {
			return [sprintf('%s does not contain an analysis object.', $rootKey)];
		}

		$analysis = $this->normalizeObject($analysis);

		$this->validateIdentity($analysis, $expectedItem, $rootKey, $errors);
		$this->validateValuation($analysis, $expectedItem, $rootKey, $errors);

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
		$this->validateAnalysisList($data, $snapshot, 'portfolioAnalysis', 'portfolio', $errors);
		$this->validateAnalysisList($data, $snapshot, 'watchlistAnalysis', 'watchlist', $errors);

		if (isset($data['stockAnalysis']) && is_array($data['stockAnalysis'])) {
			$expected = is_array($snapshot['singleStock'] ?? null)
				? $this->normalizeObject($snapshot['singleStock'])
				: [];
			$analysis = $this->normalizeObject($data['stockAnalysis']);
			$this->validateIdentity($analysis, $expected, 'stockAnalysis', $errors);
			$this->validateValuation($analysis, $expected, 'stockAnalysis', $errors);
		}

		return array_values(array_unique($errors));
	}

	/**
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $snapshot
	 * @param array<int, string> $errors
	 */
	private function validateAnalysisList(
		array $data,
		array $snapshot,
		string $responseKey,
		string $snapshotKey,
		array &$errors,
	): void
	{
		if (!isset($data[$responseKey]) || !is_array($data[$responseKey])) {
			return;
		}

		$expectedItems = is_array($snapshot[$snapshotKey] ?? null) ? $snapshot[$snapshotKey] : [];
		$expectedById = [];
		foreach ($expectedItems as $expectedItem) {
			if (is_array($expectedItem) && is_string($expectedItem['stockAssetId'] ?? null)) {
				$expectedById[$expectedItem['stockAssetId']] = $this->normalizeObject($expectedItem);
			}
		}

		$seenIds = [];
		foreach ($data[$responseKey] as $index => $item) {
			if (!is_array($item)) {
				continue;
			}

			$item = $this->normalizeObject($item);

			$path = sprintf('%s[%d]', $responseKey, $index);
			$id = is_string($item['stockAssetId'] ?? null) ? $item['stockAssetId'] : null;
			if ($id === null || !isset($expectedById[$id])) {
				$errors[] = sprintf('%s contains an unexpected stockAssetId.', $path);
				continue;
			}

			if (isset($seenIds[$id])) {
				$errors[] = sprintf('%s duplicates stockAssetId %s.', $path, $id);
				continue;
			}

			$seenIds[$id] = true;

			$this->validateIdentity($item, $expectedById[$id], $path, $errors);
			$this->validateValuation($item, $expectedById[$id], $path, $errors);
		}

		foreach (array_keys($expectedById) as $expectedId) {
			if (!isset($seenIds[$expectedId])) {
				$errors[] = sprintf('%s is missing stockAssetId %s.', $responseKey, $expectedId);
			}
		}
	}

	/**
	 * @param array<string, mixed> $item
	 * @param array<string, mixed> $expected
	 * @param array<int, string> $errors
	 */
	private function validateIdentity(array $item, array $expected, string $path, array &$errors): void
	{
		foreach (['stockAssetId', 'stockAssetName', 'stockAssetTicker'] as $key) {
			if (($item[$key] ?? null) !== ($expected[$key] ?? null)) {
				$errors[] = sprintf('%s.%s must match the immutable input snapshot.', $path, $key);
			}
		}
	}

	/**
	 * @param array<string, mixed> $item
	 * @param array<string, mixed> $expected
	 * @param array<int, string> $errors
	 */
	private function validateValuation(array $item, array $expected, string $path, array &$errors): void
	{
		$valuation = is_array($item['valuation'] ?? null) ? $item['valuation'] : [];
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
				$errors[] = sprintf(
					'%s.valuation.assessment must be uncertain when no fair value is available.',
					$path,
				);
			}

			return;
		}

		$low = TypeValidator::validateFloat($values[0]);
		$base = TypeValidator::validateFloat($values[1]);
		$high = TypeValidator::validateFloat($values[2]);
		if ($low <= 0 || $base <= 0 || $high <= 0 || $low > $base || $base > $high) {
			$errors[] = sprintf('%s.valuation must satisfy 0 < low <= base <= high.', $path);
		}

		$expectedCurrency = is_string($expected['currency'] ?? null) ? $expected['currency'] : null;
		if ($expectedCurrency !== null && $values[3] !== $expectedCurrency) {
			$errors[] = sprintf('%s.valuation.currency must equal %s.', $path, $expectedCurrency);
		}

		$dataQuality = is_array($item['dataQuality'] ?? null) ? $item['dataQuality'] : [];
		if (($dataQuality['status'] ?? null) === 'insufficient') {
			$errors[] = sprintf('%s.valuation must be null when dataQuality.status is insufficient.', $path);
		}
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
				throw new StockAiAnalysisV2ValidationException(['Expected a JSON object with string keys.']);
			}

			$result[$key] = $value;
		}

		return $result;
	}

}
