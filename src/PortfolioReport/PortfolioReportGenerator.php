<?php

declare(strict_types = 1);

namespace App\PortfolioReport;

use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Goal\PortfolioGoal;
use App\Goal\PortfolioGoalRepository;
use App\Statistic\PortfolioStatisticRecordRepository;
use App\Statistic\PortolioStatisticType;
use App\Stock\Asset\StockAsset;
use App\Stock\Dividend\Record\StockAssetDividendRecordRepository;
use App\Stock\Position\StockPosition;
use App\Stock\Position\StockPositionRepository;
use App\Stock\Price\StockAssetPriceRecordRepository;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class PortfolioReportGenerator
{

	public function __construct(
		private readonly PortfolioStatisticRecordRepository $portfolioStatisticRecordRepository,
		private readonly StockAssetPriceRecordRepository $stockAssetPriceRecordRepository,
		private readonly StockPositionRepository $stockPositionRepository,
		private readonly StockAssetDividendRecordRepository $stockAssetDividendRecordRepository,
		private readonly PortfolioGoalRepository $portfolioGoalRepository,
		private readonly CurrencyConversionFacade $currencyConversionFacade,
		private readonly PortfolioReportPromptGenerator $portfolioReportPromptGenerator,
		private readonly DatetimeFactory $datetimeFactory,
	)
	{
	}

	public function generate(PortfolioReport $portfolioReport, ImmutableDateTime $now): PortfolioReportGenerationResult
	{
		$dateFrom = $portfolioReport->getDateFrom();
		$dateTo = $portfolioReport->getDateTo();

		$portfolioValueStartCzk = $this->resolvePortfolioStatisticValue(
			$dateFrom,
			PortolioStatisticType::TOTAL_VALUE_IN_CZK,
		);
		$portfolioValueEndCzk = $this->resolvePortfolioStatisticValue(
			$dateTo,
			PortolioStatisticType::TOTAL_VALUE_IN_CZK,
		);
		$investedAmountStartCzk = $this->resolvePortfolioStatisticValue(
			$dateFrom,
			PortolioStatisticType::TOTAL_INVESTED_IN_CZK,
		);
		$investedAmountEndCzk = $this->resolvePortfolioStatisticValue(
			$dateTo,
			PortolioStatisticType::TOTAL_INVESTED_IN_CZK,
		);

		$dividendData = $this->createDividendItems($portfolioReport, $dateFrom, $dateTo, $now);
		$goalProgressItems = $this->createGoalProgressItems($portfolioReport, $dateFrom, $dateTo, $now);
		$assetPerformances = $this->createAssetPerformanceItems(
			$portfolioReport,
			$dateFrom,
			$dateTo,
			$portfolioValueStartCzk,
			$portfolioValueEndCzk,
			$now,
		);

		$goalsProgressSummary = $this->createGoalsProgressSummary($goalProgressItems);
		$summaryText = $this->createSummaryText(
			$portfolioValueStartCzk,
			$portfolioValueEndCzk,
			$dividendData['totalCzk'],
			$goalsProgressSummary,
		);

		$payload = [
			'period' => [
				'type' => $portfolioReport->getPeriodType()->value,
				'dateFrom' => $dateFrom->format('Y-m-d'),
				'dateTo' => $dateTo->format('Y-m-d'),
			],
			'portfolioSummary' => [
				'portfolioValueStartCzk' => round($portfolioValueStartCzk, 2),
				'portfolioValueEndCzk' => round($portfolioValueEndCzk, 2),
				'portfolioValueDiffCzk' => round($portfolioValueEndCzk - $portfolioValueStartCzk, 2),
				'portfolioValueDiffPercentage' => round(
					$this->calculatePercentageDiff($portfolioValueStartCzk, $portfolioValueEndCzk),
					2,
				),
				'investedAmountStartCzk' => round($investedAmountStartCzk, 2),
				'investedAmountEndCzk' => round($investedAmountEndCzk, 2),
				'investedAmountDiffCzk' => round($investedAmountEndCzk - $investedAmountStartCzk, 2),
				'dividendsTotalCzk' => round($dividendData['totalCzk'], 2),
			],
			'priceWinners' => $this->mapAssetPerformancesForPrompt(
				$assetPerformances,
				PortfolioReportAssetRankingTypeEnum::PRICE,
				PortfolioReportAssetRankingDirectionEnum::WINNER,
			),
			'priceLosers' => $this->mapAssetPerformancesForPrompt(
				$assetPerformances,
				PortfolioReportAssetRankingTypeEnum::PRICE,
				PortfolioReportAssetRankingDirectionEnum::LOSER,
			),
			'contributionWinners' => $this->mapAssetPerformancesForPrompt(
				$assetPerformances,
				PortfolioReportAssetRankingTypeEnum::CONTRIBUTION,
				PortfolioReportAssetRankingDirectionEnum::WINNER,
			),
			'contributionLosers' => $this->mapAssetPerformancesForPrompt(
				$assetPerformances,
				PortfolioReportAssetRankingTypeEnum::CONTRIBUTION,
				PortfolioReportAssetRankingDirectionEnum::LOSER,
			),
			'dividends' => $dividendData['promptItems'],
			'goals' => $this->mapGoalsForPrompt($goalProgressItems),
			'goalsSummary' => $goalsProgressSummary,
			'summaryText' => $summaryText,
		];

		return new PortfolioReportGenerationResult(
			$portfolioValueStartCzk,
			$portfolioValueEndCzk,
			$investedAmountStartCzk,
			$investedAmountEndCzk,
			$dividendData['totalCzk'],
			$goalsProgressSummary,
			$summaryText,
			$this->portfolioReportPromptGenerator->generate($portfolioReport, $payload),
			$assetPerformances,
			$dividendData['items'],
			$goalProgressItems,
			$payload,
		);
	}

	private function resolvePortfolioStatisticValue(ImmutableDateTime $date, PortolioStatisticType $type): float
	{
		$record = $this->portfolioStatisticRecordRepository->findLatestByCreatedAtAtOrBefore($date);
		if ($record === null) {
			$record = $this->portfolioStatisticRecordRepository->findEarliestByCreatedAtAtOrAfter($date);
		}

		if ($record === null) {
			return 0.0;
		}

		$portfolioStatistic = $record->getPortfolioStatisticByType($type);
		if ($portfolioStatistic === null) {
			return 0.0;
		}

		return $this->normalizeNumericValue($portfolioStatistic->getValue());
	}

	/**
	 * @return array{items: array<int, PortfolioReportDividend>, totalCzk: float, promptItems: array<int, array<string, mixed>>}
	 */
	private function createDividendItems(
		PortfolioReport $portfolioReport,
		ImmutableDateTime $dateFrom,
		ImmutableDateTime $dateTo,
		ImmutableDateTime $now,
	): array
	{
		$items = [];
		$promptItems = [];
		$totalCzk = 0.0;

		foreach ($this->stockAssetDividendRecordRepository->findBetweenDates($dateFrom, $dateTo) as $dividendRecord) {
			$paymentDate = $dividendRecord->getStockAssetDividend()->getPaymentDate() ?? $dividendRecord->getStockAssetDividend()->getExDate();
			$taxPercentage = $dividendRecord->getDividendTax();
			$grossAmount = $dividendRecord->getTotalAmount();
			$netAmount = $taxPercentage === null
				? null
				: $grossAmount * (1 - ($taxPercentage * 0.01));
			$amountForConversion = $netAmount ?? $grossAmount;
			$amountCzk = $this->currencyConversionFacade->convertSimpleValue(
				$amountForConversion,
				$dividendRecord->getCurrency(),
				CurrencyEnum::CZK,
				$paymentDate,
			);

			$item = new PortfolioReportDividend(
				$portfolioReport,
				$dividendRecord->getStockAssetDividend()->getStockAsset()->getTicker(),
				$dividendRecord->getStockAssetDividend()->getStockAsset()->getName(),
				$paymentDate,
				$grossAmount,
				$dividendRecord->getCurrency(),
				$amountCzk,
				$netAmount,
				$taxPercentage,
				$now,
			);

			$items[] = $item;
			$totalCzk += $amountCzk;
			$promptItems[] = [
				'ticker' => $item->getTicker(),
				'name' => $item->getName(),
				'paymentDate' => $item->getPaymentDate()->format('Y-m-d'),
				'amountInSourceCurrency' => round($item->getAmountInSourceCurrency(), 4),
				'sourceCurrency' => $item->getSourceCurrency()->value,
				'amountCzk' => round($item->getAmountCzk(), 2),
				'netAmount' => $item->getNetAmount() !== null ? round($item->getNetAmount(), 4) : null,
			];
		}

		return [
			'items' => $items,
			'totalCzk' => $totalCzk,
			'promptItems' => $promptItems,
		];
	}

	/** @return array<int, PortfolioReportGoalProgress> */
	private function createGoalProgressItems(
		PortfolioReport $portfolioReport,
		ImmutableDateTime $dateFrom,
		ImmutableDateTime $dateTo,
		ImmutableDateTime $now,
	): array
	{
		$items = [];

		foreach ($this->portfolioGoalRepository->findAll() as $portfolioGoal) {
			if ($portfolioGoal->getStartDate() > $dateTo || $portfolioGoal->getEndDate() < $dateFrom) {
				continue;
			}

			$startValue = $this->resolveGoalValueForDate($portfolioGoal, $dateFrom);
			$endValue = $this->resolveGoalValueForDate($portfolioGoal, $dateTo);
			$summary = $endValue >= $startValue
				? sprintf('Goal improved by %.2f.', $endValue - $startValue)
				: sprintf('Goal declined by %.2f.', $startValue - $endValue);

			$items[] = new PortfolioReportGoalProgress(
				$portfolioReport,
				$portfolioGoal,
				$portfolioGoal->getType(),
				$startValue,
				$endValue,
				$portfolioGoal->getGoal(),
				$summary,
				$now,
			);
		}

		return $items;
	}

	/** @return array<int, PortfolioReportAssetPerformance> */
	private function createAssetPerformanceItems(
		PortfolioReport $portfolioReport,
		ImmutableDateTime $dateFrom,
		ImmutableDateTime $dateTo,
		float $portfolioValueStartCzk,
		float $portfolioValueEndCzk,
		ImmutableDateTime $now,
	): array
	{
		$groupedPositions = [];
		foreach ($this->stockPositionRepository->findAll() as $position) {
			if ($position->getOrderDate() > $dateTo) {
				continue;
			}

			$closedPosition = $position->getStockClosedPosition();
			if ($closedPosition !== null && $closedPosition->getDate() < $dateFrom) {
				continue;
			}

			$stockAsset = $position->getAsset();
			assert($stockAsset instanceof StockAsset);
			$groupedPositions[$stockAsset->getTicker()][] = $position;
		}

		$assetMetrics = [];
		foreach ($groupedPositions as $positions) {
			$assetMetrics[] = $this->buildAssetMetric(
				$positions,
				$dateFrom,
				$dateTo,
				$portfolioValueStartCzk,
				$portfolioValueEndCzk,
			);
		}

		$priceWinners = $assetMetrics;
		usort(
			$priceWinners,
			static fn (array $left, array $right): int => $right['pricePercentageChange'] <=> $left['pricePercentageChange'],
		);
		$priceLosers = $assetMetrics;
		usort(
			$priceLosers,
			static fn (array $left, array $right): int => $left['pricePercentageChange'] <=> $right['pricePercentageChange'],
		);
		$contributionWinners = $assetMetrics;
		usort(
			$contributionWinners,
			static fn (array $left, array $right): int => $right['contributionToPortfolioPercentage'] <=> $left['contributionToPortfolioPercentage'],
		);
		$contributionLosers = $assetMetrics;
		usort(
			$contributionLosers,
			static fn (array $left, array $right): int => $left['contributionToPortfolioPercentage'] <=> $right['contributionToPortfolioPercentage'],
		);

		$items = [];
		foreach ($this->limitMetrics($priceWinners) as $metric) {
			$items[] = $this->createAssetPerformanceEntity(
				$portfolioReport,
				$metric,
				PortfolioReportAssetRankingTypeEnum::PRICE,
				PortfolioReportAssetRankingDirectionEnum::WINNER,
				$now,
			);
		}

		foreach ($this->limitMetrics($priceLosers) as $metric) {
			$items[] = $this->createAssetPerformanceEntity(
				$portfolioReport,
				$metric,
				PortfolioReportAssetRankingTypeEnum::PRICE,
				PortfolioReportAssetRankingDirectionEnum::LOSER,
				$now,
			);
		}

		foreach ($this->limitMetrics($contributionWinners) as $metric) {
			$items[] = $this->createAssetPerformanceEntity(
				$portfolioReport,
				$metric,
				PortfolioReportAssetRankingTypeEnum::CONTRIBUTION,
				PortfolioReportAssetRankingDirectionEnum::WINNER,
				$now,
			);
		}

		foreach ($this->limitMetrics($contributionLosers) as $metric) {
			$items[] = $this->createAssetPerformanceEntity(
				$portfolioReport,
				$metric,
				PortfolioReportAssetRankingTypeEnum::CONTRIBUTION,
				PortfolioReportAssetRankingDirectionEnum::LOSER,
				$now,
			);
		}

		return $items;
	}

	/**
	 * @param array<int, StockPosition> $positions
	 * @return array<string, float|int|string|CurrencyEnum>
	 */
	private function buildAssetMetric(
		array $positions,
		ImmutableDateTime $dateFrom,
		ImmutableDateTime $dateTo,
		float $portfolioValueStartCzk,
		float $portfolioValueEndCzk,
	): array
	{
		$firstPosition = $positions[0];
		$stockAsset = $firstPosition->getAsset();
		assert($stockAsset instanceof StockAsset);

		$priceStartMeta = $this->resolveAssetStartPriceMeta($stockAsset, $positions, $dateFrom);
		$priceEndMeta = $this->resolveAssetEndPriceMeta($stockAsset, $positions, $dateTo);

		$positionValueStartCzk = 0.0;
		$positionValueEndCzk = 0.0;
		foreach ($positions as $position) {
			$positionValueStartCzk += $this->resolvePositionValueAtStart($position, $dateFrom);
			$positionValueEndCzk += $this->resolvePositionValueAtEnd($position, $dateTo);
		}

		$denominator = $portfolioValueStartCzk > 0.0 ? $portfolioValueStartCzk : max($portfolioValueEndCzk, 1.0);

		return [
			'ticker' => $stockAsset->getTicker(),
			'name' => $stockAsset->getName(),
			'baseCurrency' => $stockAsset->getCurrency(),
			'priceStartInBaseCurrency' => $priceStartMeta['priceBase'],
			'priceEndInBaseCurrency' => $priceEndMeta['priceBase'],
			'priceStartCzk' => $priceStartMeta['priceCzk'],
			'priceEndCzk' => $priceEndMeta['priceCzk'],
			'pricePercentageChange' => $this->calculatePercentageDiff(
				$priceStartMeta['priceBase'],
				$priceEndMeta['priceBase'],
			),
			'positionValueStartCzk' => $positionValueStartCzk,
			'positionValueEndCzk' => $positionValueEndCzk,
			'contributionToPortfolioPercentage' => ($positionValueEndCzk - $positionValueStartCzk) / $denominator * 100,
		];
	}

	/**
	 * @param array<int, StockPosition> $positions
	 * @return array{priceBase: float, priceCzk: float, referenceDate: ImmutableDateTime}
	 */
	private function resolveAssetStartPriceMeta(
		StockAsset $stockAsset,
		array $positions,
		ImmutableDateTime $dateFrom,
	): array
	{
		$referenceDate = $dateFrom;
		foreach ($positions as $position) {
			if (
				$position->getOrderDate() > $referenceDate
				&& $position->getOrderDate() <= $this->datetimeFactory->createNow()
			) {
				$referenceDate = $position->getOrderDate();
				break;
			}
		}

		if ($referenceDate > $dateFrom) {
			$position = $positions[0];
			$priceBase = $position->getPricePerPiece()->getPrice();
			$priceCzk = $this->currencyConversionFacade->convertSimpleValue(
				$priceBase,
				$stockAsset->getCurrency(),
				CurrencyEnum::CZK,
				$referenceDate,
			);

			return [
				'priceBase' => $priceBase,
				'priceCzk' => $priceCzk,
				'referenceDate' => $referenceDate,
			];
		}

		$priceRecord = $this->stockAssetPriceRecordRepository->findClosestByStockAssetAndDate($stockAsset, $dateFrom);
		if ($priceRecord !== null) {
			return [
				'priceBase' => $priceRecord->getPrice(),
				'priceCzk' => $this->currencyConversionFacade->convertSimpleValue(
					$priceRecord->getPrice(),
					$stockAsset->getCurrency(),
					CurrencyEnum::CZK,
					$priceRecord->getDate(),
				),
				'referenceDate' => $priceRecord->getDate(),
			];
		}

		$currentPrice = $stockAsset->getAssetCurrentPrice()->getPrice();

		return [
			'priceBase' => $currentPrice,
			'priceCzk' => $this->currencyConversionFacade->convertSimpleValue(
				$currentPrice,
				$stockAsset->getCurrency(),
				CurrencyEnum::CZK,
				$dateFrom,
			),
			'referenceDate' => $dateFrom,
		];
	}

	/**
	 * @param array<int, StockPosition> $positions
	 * @return array{priceBase: float, priceCzk: float, referenceDate: ImmutableDateTime}
	 */
	private function resolveAssetEndPriceMeta(
		StockAsset $stockAsset,
		array $positions,
		ImmutableDateTime $dateTo,
	): array
	{
		$latestClosedPosition = null;
		foreach ($positions as $position) {
			$closedPosition = $position->getStockClosedPosition();
			if ($closedPosition === null || $closedPosition->getDate() > $dateTo) {
				continue;
			}

			if ($latestClosedPosition === null || $closedPosition->getDate() > $latestClosedPosition->getDate()) {
				$latestClosedPosition = $closedPosition;
			}
		}

		if ($latestClosedPosition !== null) {
			$priceBase = $latestClosedPosition->getPricePerPiece()->getPrice();

			return [
				'priceBase' => $priceBase,
				'priceCzk' => $this->currencyConversionFacade->convertSimpleValue(
					$priceBase,
					$stockAsset->getCurrency(),
					CurrencyEnum::CZK,
					$latestClosedPosition->getDate(),
				),
				'referenceDate' => $latestClosedPosition->getDate(),
			];
		}

		$priceRecord = $this->stockAssetPriceRecordRepository->findClosestByStockAssetAndDate($stockAsset, $dateTo);
		if ($priceRecord !== null) {
			return [
				'priceBase' => $priceRecord->getPrice(),
				'priceCzk' => $this->currencyConversionFacade->convertSimpleValue(
					$priceRecord->getPrice(),
					$stockAsset->getCurrency(),
					CurrencyEnum::CZK,
					$priceRecord->getDate(),
				),
				'referenceDate' => $priceRecord->getDate(),
			];
		}

		$currentPrice = $stockAsset->getAssetCurrentPrice()->getPrice();

		return [
			'priceBase' => $currentPrice,
			'priceCzk' => $this->currencyConversionFacade->convertSimpleValue(
				$currentPrice,
				$stockAsset->getCurrency(),
				CurrencyEnum::CZK,
				$dateTo,
			),
			'referenceDate' => $dateTo,
		];
	}

	private function resolvePositionValueAtStart(StockPosition $position, ImmutableDateTime $dateFrom): float
	{
		if ($position->getOrderDate() > $dateFrom) {
			return 0.0;
		}

		$stockAsset = $position->getAsset();
		assert($stockAsset instanceof StockAsset);
		$priceRecord = $this->stockAssetPriceRecordRepository->findClosestByStockAssetAndDate($stockAsset, $dateFrom);
		$priceBase = $priceRecord?->getPrice() ?? $position->getPricePerPiece()->getPrice();

		return $this->currencyConversionFacade->convertSimpleValue(
			$priceBase * $position->getOrderPiecesCount(),
			$stockAsset->getCurrency(),
			CurrencyEnum::CZK,
			$dateFrom,
		);
	}

	private function resolvePositionValueAtEnd(StockPosition $position, ImmutableDateTime $dateTo): float
	{
		$stockAsset = $position->getAsset();
		assert($stockAsset instanceof StockAsset);

		$closedPosition = $position->getStockClosedPosition();
		if ($closedPosition !== null && $closedPosition->getDate() <= $dateTo) {
			return $this->currencyConversionFacade->convertSimpleValue(
				$closedPosition->getPricePerPiece()->getPrice() * $position->getOrderPiecesCount(),
				$stockAsset->getCurrency(),
				CurrencyEnum::CZK,
				$closedPosition->getDate(),
			);
		}

		$priceRecord = $this->stockAssetPriceRecordRepository->findClosestByStockAssetAndDate($stockAsset, $dateTo);
		$priceBase = $priceRecord?->getPrice() ?? $stockAsset->getAssetCurrentPrice()->getPrice();

		return $this->currencyConversionFacade->convertSimpleValue(
			$priceBase * $position->getOrderPiecesCount(),
			$stockAsset->getCurrency(),
			CurrencyEnum::CZK,
			$dateTo,
		);
	}

	private function resolveGoalValueForDate(PortfolioGoal $portfolioGoal, ImmutableDateTime $boundaryDate): float
	{
		$statistics = $portfolioGoal->getStatistics();
		ksort($statistics);
		$boundaryDateKey = $boundaryDate->format('Y-m-d');
		$closestValue = null;

		foreach ($statistics as $dateKey => $value) {
			if ($dateKey > $boundaryDateKey) {
				break;
			}

			$closestValue = $value;
		}

		if ($closestValue !== null) {
			return $closestValue;
		}

		if ($boundaryDate <= $portfolioGoal->getStartDate()) {
			return $portfolioGoal->getValueAtStart();
		}

		if ($boundaryDate >= $portfolioGoal->getEndDate()) {
			return $portfolioGoal->getValueAtEnd();
		}

		return $portfolioGoal->getCurrentValue();
	}

	/**
	 * @param array<int, PortfolioReportGoalProgress> $goalProgressItems
	 */
	private function createGoalsProgressSummary(array $goalProgressItems): string|null
	{
		if ($goalProgressItems === []) {
			return null;
		}

		$positiveCount = 0;
		$negativeCount = 0;
		foreach ($goalProgressItems as $goalProgressItem) {
			if ($goalProgressItem->getCompletionPercentageDiff() >= 0) {
				$positiveCount++;
			} else {
				$negativeCount++;
			}
		}

		return sprintf(
			'Positive goals: %d, negative goals: %d.',
			$positiveCount,
			$negativeCount,
		);
	}

	private function createSummaryText(
		float $portfolioValueStartCzk,
		float $portfolioValueEndCzk,
		float $dividendsTotalCzk,
		string|null $goalsProgressSummary,
	): string
	{
		$portfolioDiffPercentage = round(
			$this->calculatePercentageDiff($portfolioValueStartCzk, $portfolioValueEndCzk),
			2,
		);
		$direction = $portfolioValueEndCzk >= $portfolioValueStartCzk ? 'grew' : 'declined';

		return trim(sprintf(
			'Portfolio %s by %s %% during the period. Dividends received: %.2f CZK. %s',
			$direction,
			$portfolioDiffPercentage,
			$dividendsTotalCzk,
			$goalsProgressSummary ?? '',
		));
	}

	/**
	 * @param array<int, PortfolioReportAssetPerformance> $assetPerformances
	 * @return array<int, array<string, mixed>>
	 */
	private function mapAssetPerformancesForPrompt(
		array $assetPerformances,
		PortfolioReportAssetRankingTypeEnum $rankingType,
		PortfolioReportAssetRankingDirectionEnum $direction,
	): array
	{
		$items = [];
		foreach ($assetPerformances as $assetPerformance) {
			if (
				$assetPerformance->getRankingType() !== $rankingType
				|| $assetPerformance->getDirection() !== $direction
			) {
				continue;
			}

			$items[] = [
				'ticker' => $assetPerformance->getTicker(),
				'name' => $assetPerformance->getName(),
				'priceChangePercentage' => round($assetPerformance->getPricePercentageChange(), 2),
				'priceChangeAbsolute' => round($assetPerformance->getPriceAbsoluteChange(), 4),
				'positionChangeCzk' => round($assetPerformance->getPositionAbsoluteChangeCzk(), 2),
				'contributionToPortfolioPercentage' => round(
					$assetPerformance->getContributionToPortfolioPercentage(),
					2,
				),
			];
		}

		return $items;
	}

	/**
	 * @param array<int, PortfolioReportGoalProgress> $goalProgressItems
	 * @return array<int, array<string, mixed>>
	 */
	private function mapGoalsForPrompt(array $goalProgressItems): array
	{
		$items = [];
		foreach ($goalProgressItems as $goalProgressItem) {
			$items[] = [
				'type' => $goalProgressItem->getGoalType()->value,
				'goalStartValue' => round($goalProgressItem->getGoalStartValue(), 2),
				'goalEndValue' => round($goalProgressItem->getGoalEndValue(), 2),
				'goalTargetValue' => round($goalProgressItem->getGoalTargetValue(), 2),
				'completionPercentageStart' => round($goalProgressItem->getCompletionPercentageStart(), 2),
				'completionPercentageEnd' => round($goalProgressItem->getCompletionPercentageEnd(), 2),
				'completionPercentageDiff' => round($goalProgressItem->getCompletionPercentageDiff(), 2),
				'summary' => $goalProgressItem->getSummary(),
			];
		}

		return $items;
	}

	/**
	 * @param array<int, array<string, float|int|string|CurrencyEnum>> $metrics
	 * @return array<int, array<string, float|int|string|CurrencyEnum>>
	 */
	private function limitMetrics(array $metrics): array
	{
		return array_slice($metrics, 0, 5);
	}

	/**
	 * @param array<string, float|int|string|CurrencyEnum> $metric
	 */
	private function createAssetPerformanceEntity(
		PortfolioReport $portfolioReport,
		array $metric,
		PortfolioReportAssetRankingTypeEnum $rankingType,
		PortfolioReportAssetRankingDirectionEnum $direction,
		ImmutableDateTime $now,
	): PortfolioReportAssetPerformance
	{
		$ticker = $metric['ticker'];
		$name = $metric['name'];
		$baseCurrency = $metric['baseCurrency'];
		$priceStartInBaseCurrency = $metric['priceStartInBaseCurrency'];
		$priceEndInBaseCurrency = $metric['priceEndInBaseCurrency'];
		$priceStartCzk = $metric['priceStartCzk'];
		$priceEndCzk = $metric['priceEndCzk'];
		$positionValueStartCzk = $metric['positionValueStartCzk'];
		$positionValueEndCzk = $metric['positionValueEndCzk'];
		$contributionToPortfolioPercentage = $metric['contributionToPortfolioPercentage'];

		assert(is_string($ticker));
		assert(is_string($name));
		assert($baseCurrency instanceof CurrencyEnum);
		assert(is_float($priceStartInBaseCurrency));
		assert(is_float($priceEndInBaseCurrency));
		assert(is_float($priceStartCzk));
		assert(is_float($priceEndCzk));
		assert(is_float($positionValueStartCzk));
		assert(is_float($positionValueEndCzk));
		assert(is_float($contributionToPortfolioPercentage));

		return new PortfolioReportAssetPerformance(
			$portfolioReport,
			$rankingType,
			$direction,
			$ticker,
			$name,
			$baseCurrency,
			$priceStartInBaseCurrency,
			$priceEndInBaseCurrency,
			$priceStartCzk,
			$priceEndCzk,
			$positionValueStartCzk,
			$positionValueEndCzk,
			$contributionToPortfolioPercentage,
			$now,
		);
	}

	private function calculatePercentageDiff(float $startValue, float $endValue): float
	{
		if ($startValue === 0.0) {
			return 0.0;
		}

		return ($endValue - $startValue) / $startValue * 100;
	}

	private function normalizeNumericValue(string $value): float
	{
		$normalizedValue = str_replace(['CZK', '%', ' '], '', $value);

		return (float) str_replace(',', '.', $normalizedValue);
	}

}
