<?php

declare(strict_types = 1);

namespace App\Mcp\Tool;

use App\Mcp\Support\McpInvalidInputException;
use App\Mcp\Support\StockAssetResolver;
use App\Stock\Price\StockAssetPriceRecord;
use App\Stock\Price\StockAssetPriceRecordRepository;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use const DATE_ATOM;

class StockPriceHistoryTool
{

	private const int MIN_POINTS = 2;

	private const int MAX_POINTS = 365;

	public function __construct(
		private readonly StockAssetResolver $stockAssetResolver,
		private readonly StockAssetPriceRecordRepository $priceRecordRepository,
		private readonly DatetimeFactory $datetimeFactory,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function execute(
		string|null $ticker = null,
		string|null $stockAssetId = null,
		string $period = '6M',
		int $maxPoints = 90,
	): array
	{
		if ($maxPoints < self::MIN_POINTS || $maxPoints > self::MAX_POINTS) {
			throw new McpInvalidInputException(
				sprintf('maxPoints must be between %d and %d.', self::MIN_POINTS, self::MAX_POINTS),
			);
		}

		$normalizedPeriod = strtoupper(trim($period));
		$now = $this->datetimeFactory->createNow();
		$since = $this->resolveSinceDate($normalizedPeriod, $now);

		$asset = $this->stockAssetResolver->resolve($ticker, $stockAssetId);
		$records = array_values($this->priceRecordRepository->findByStockAssetSinceDate($asset, $since));

		$fiftyTwoWeekStart = $now->deductDaysFromDatetime(365);
		if (in_array($normalizedPeriod, ['1Y', 'ALL'], true)) {
			$fiftyTwoWeekRecords = array_values(array_filter(
				$records,
				static fn (StockAssetPriceRecord $record): bool => $record->getDate() >= $fiftyTwoWeekStart,
			));
		} else {
			$fiftyTwoWeekRecords = array_values(
				$this->priceRecordRepository->findByStockAssetSinceDate($asset, $fiftyTwoWeekStart),
			);
		}

		$currentPrice = $asset->getAssetCurrentPrice()->getPrice();
		$sampledRecords = $this->downsample($records, $maxPoints);

		return [
			'generatedAt' => $now->format(DATE_ATOM),
			'stock' => [
				'stockAssetId' => $asset->getId()->toString(),
				'ticker' => $asset->getTicker(),
				'name' => $asset->getName(),
				'currency' => $asset->getCurrency()->value,
				'currentPrice' => round($currentPrice, 4),
				'priceDownloadedAt' => $asset->getPriceDownloadedAt()?->format(DATE_ATOM),
			],
			'period' => $normalizedPeriod,
			'periodStats' => $this->buildPeriodStats($records, count($sampledRecords)),
			'fiftyTwoWeek' => $this->buildFiftyTwoWeekStats($fiftyTwoWeekRecords, $currentPrice),
			'points' => array_map(
				static fn (StockAssetPriceRecord $record): array => [
					'date' => $record->getDate()->format('Y-m-d'),
					'price' => round($record->getPrice(), 4),
				],
				$sampledRecords,
			),
		];
	}

	private function resolveSinceDate(string $period, ImmutableDateTime $now): ImmutableDateTime
	{
		return match ($period) {
			'1M' => $now->deductDaysFromDatetime(30),
			'3M' => $now->deductDaysFromDatetime(90),
			'6M' => $now->deductDaysFromDatetime(180),
			'1Y' => $now->deductDaysFromDatetime(365),
			'ALL' => new ImmutableDateTime('1970-01-01 00:00:00'),
			default => throw new McpInvalidInputException(
				sprintf('Unsupported period "%s". Allowed values: 1M, 3M, 6M, 1Y, ALL.', $period),
			),
		};
	}

	/**
	 * @param list<StockAssetPriceRecord> $records
	 * @return list<StockAssetPriceRecord>
	 */
	private function downsample(array $records, int $maxPoints): array
	{
		$count = count($records);
		if ($count <= $maxPoints) {
			return $records;
		}

		$result = [];
		$step = ($count - 1) / ($maxPoints - 1);
		for ($i = 0; $i < $maxPoints - 1; $i++) {
			$index = (int) round($i * $step);
			$result[] = $records[$index];
		}

		$result[] = $records[$count - 1];

		return $result;
	}

	/**
	 * @param list<StockAssetPriceRecord> $records
	 * @return array<string, float|int|string|null>
	 */
	private function buildPeriodStats(array $records, int $returnedPointsCount): array
	{
		if ($records === []) {
			return [
				'startDate' => null,
				'endDate' => null,
				'startPrice' => null,
				'endPrice' => null,
				'changePercent' => null,
				'minPrice' => null,
				'maxPrice' => null,
				'totalRecordsCount' => 0,
				'returnedPointsCount' => 0,
			];
		}

		$first = $records[0];
		$last = $records[count($records) - 1];
		$prices = array_map(static fn (StockAssetPriceRecord $record): float => $record->getPrice(), $records);
		$startPrice = $first->getPrice();
		$endPrice = $last->getPrice();
		$changePercent = $startPrice > 0.0 ? ($endPrice - $startPrice) / $startPrice * 100 : null;

		return [
			'startDate' => $first->getDate()->format('Y-m-d'),
			'endDate' => $last->getDate()->format('Y-m-d'),
			'startPrice' => round($startPrice, 4),
			'endPrice' => round($endPrice, 4),
			'changePercent' => $changePercent !== null ? round($changePercent, 2) : null,
			'minPrice' => round(min($prices), 4),
			'maxPrice' => round(max($prices), 4),
			'totalRecordsCount' => count($records),
			'returnedPointsCount' => $returnedPointsCount,
		];
	}

	/**
	 * @param list<StockAssetPriceRecord> $records
	 * @return array<string, float|null>
	 */
	private function buildFiftyTwoWeekStats(array $records, float $currentPrice): array
	{
		if ($records === []) {
			return [
				'high' => null,
				'low' => null,
				'distanceFromHighPercent' => null,
				'distanceFromLowPercent' => null,
			];
		}

		$prices = array_map(static fn (StockAssetPriceRecord $record): float => $record->getPrice(), $records);
		$high = max($prices);
		$low = min($prices);

		return [
			'high' => round($high, 4),
			'low' => round($low, 4),
			'distanceFromHighPercent' => $high > 0.0 && $currentPrice > 0.0
				? round(($currentPrice - $high) / $high * 100, 2)
				: null,
			'distanceFromLowPercent' => $low > 0.0 && $currentPrice > 0.0
				? round(($currentPrice - $low) / $low * 100, 2)
				: null,
		];
	}

}
