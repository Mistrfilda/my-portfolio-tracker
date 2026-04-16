<?php

declare(strict_types = 1);

namespace App\Test\Unit\PortfolioReport;

use App\Asset\Price\AssetPrice;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Goal\PortfolioGoal;
use App\Goal\PortfolioGoalRepository;
use App\Goal\PortfolioGoalTypeEnum;
use App\PortfolioReport\PortfolioReport;
use App\PortfolioReport\PortfolioReportAssetRankingDirectionEnum;
use App\PortfolioReport\PortfolioReportAssetRankingTypeEnum;
use App\PortfolioReport\PortfolioReportGenerator;
use App\PortfolioReport\PortfolioReportPeriodTypeEnum;
use App\PortfolioReport\PortfolioReportPromptGenerator;
use App\Statistic\PortfolioStatistic;
use App\Statistic\PortfolioStatisticRecord;
use App\Statistic\PortfolioStatisticRecordRepository;
use App\Statistic\PortolioStatisticType;
use App\Stock\Asset\StockAsset;
use App\Stock\Dividend\Record\StockAssetDividendRecord;
use App\Stock\Dividend\Record\StockAssetDividendRecordRepository;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Position\StockPosition;
use App\Stock\Position\StockPositionRepository;
use App\Stock\Price\StockAssetPriceRecord;
use App\Stock\Price\StockAssetPriceRecordRepository;
use App\Test\UpdatedTestCase;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;

class PortfolioReportGeneratorTest extends UpdatedTestCase
{

	private PortfolioReportGenerator $portfolioReportGenerator;

	private PortfolioStatisticRecordRepository $portfolioStatisticRecordRepository;

	private StockAssetPriceRecordRepository $stockAssetPriceRecordRepository;

	private StockPositionRepository $stockPositionRepository;

	private StockAssetDividendRecordRepository $stockAssetDividendRecordRepository;

	private PortfolioGoalRepository $portfolioGoalRepository;

	private CurrencyConversionFacade $currencyConversionFacade;

	private PortfolioReportPromptGenerator $portfolioReportPromptGenerator;

	protected function setUp(): void
	{
		parent::setUp();
		$this->portfolioStatisticRecordRepository = self::createMockWithIgnoreMethods(
			PortfolioStatisticRecordRepository::class,
		);
		$this->stockAssetPriceRecordRepository = self::createMockWithIgnoreMethods(
			StockAssetPriceRecordRepository::class,
		);
		$this->stockPositionRepository = self::createMockWithIgnoreMethods(StockPositionRepository::class);
		$this->stockAssetDividendRecordRepository = self::createMockWithIgnoreMethods(
			StockAssetDividendRecordRepository::class,
		);
		$this->portfolioGoalRepository = self::createMockWithIgnoreMethods(PortfolioGoalRepository::class);
		$this->currencyConversionFacade = self::createMockWithIgnoreMethods(CurrencyConversionFacade::class);
		$this->portfolioReportPromptGenerator = self::createMockWithIgnoreMethods(
			PortfolioReportPromptGenerator::class,
		);

		$this->portfolioReportGenerator = new PortfolioReportGenerator(
			$this->portfolioStatisticRecordRepository,
			$this->stockAssetPriceRecordRepository,
			$this->stockPositionRepository,
			$this->stockAssetDividendRecordRepository,
			$this->portfolioGoalRepository,
			$this->currencyConversionFacade,
			$this->portfolioReportPromptGenerator,
			self::createMockWithIgnoreMethods(DatetimeFactory::class),
		);
	}

	public function testGenerateBuildsSummaryDividendsGoalsAndRankings(): void
	{
		$dateFrom = new ImmutableDateTime('2026-04-01 00:00:01');
		$dateTo = new ImmutableDateTime('2026-04-30 23:59:59');
		$now = new ImmutableDateTime('2026-04-30 12:00:00');
		$portfolioReport = new PortfolioReport(
			PortfolioReportPeriodTypeEnum::MONTHLY,
			$dateFrom,
			$dateTo,
			$now,
		);

		$startRecord = self::createMockWithIgnoreMethods(PortfolioStatisticRecord::class);
		$endRecord = self::createMockWithIgnoreMethods(PortfolioStatisticRecord::class);
		$startValueStatistic = self::createMockWithIgnoreMethods(PortfolioStatistic::class);
		$endValueStatistic = self::createMockWithIgnoreMethods(PortfolioStatistic::class);
		$startInvestedStatistic = self::createMockWithIgnoreMethods(PortfolioStatistic::class);
		$endInvestedStatistic = self::createMockWithIgnoreMethods(PortfolioStatistic::class);

		$startValueStatistic->shouldReceive('getValue')->andReturn('1 000 CZK');
		$endValueStatistic->shouldReceive('getValue')->andReturn('1 250 CZK');
		$startInvestedStatistic->shouldReceive('getValue')->andReturn('800 CZK');
		$endInvestedStatistic->shouldReceive('getValue')->andReturn('900 CZK');

		$startRecord->shouldReceive('getPortfolioStatisticByType')
			->with(PortolioStatisticType::TOTAL_VALUE_IN_CZK)
			->andReturn($startValueStatistic);
		$startRecord->shouldReceive('getPortfolioStatisticByType')
			->with(PortolioStatisticType::TOTAL_INVESTED_IN_CZK)
			->andReturn($startInvestedStatistic);
		$endRecord->shouldReceive('getPortfolioStatisticByType')
			->with(PortolioStatisticType::TOTAL_VALUE_IN_CZK)
			->andReturn($endValueStatistic);
		$endRecord->shouldReceive('getPortfolioStatisticByType')
			->with(PortolioStatisticType::TOTAL_INVESTED_IN_CZK)
			->andReturn($endInvestedStatistic);

		$this->portfolioStatisticRecordRepository->shouldReceive('findLatestByCreatedAtAtOrBefore')
			->times(4)
			->andReturnUsing(
				static fn (ImmutableDateTime $date): PortfolioStatisticRecord => $date->format(
					'Y-m-d',
				) === $dateFrom->format(
					'Y-m-d',
				) ? $startRecord : $endRecord,
			);
		$this->portfolioStatisticRecordRepository->shouldReceive('findEarliestByCreatedAtAtOrAfter')
			->never();

		[$assetA, $positionA, $assetAStartRecord, $assetAEndRecord] = $this->createPositionPriceScenario(
			'AAA',
			'Alpha Inc.',
			100.0,
			120.0,
			$dateFrom,
			$dateTo,
		);
		[$assetB, $positionB, $assetBStartRecord, $assetBEndRecord] = $this->createPositionPriceScenario(
			'BBB',
			'Beta Inc.',
			100.0,
			80.0,
			$dateFrom,
			$dateTo,
		);

		$this->stockPositionRepository->shouldReceive('findAll')
			->once()
			->andReturn([$positionA, $positionB]);

		$this->stockAssetPriceRecordRepository->shouldReceive('findClosestByStockAssetAndDate')
			->andReturnUsing(
				static function (
					StockAsset $stockAsset,
					ImmutableDateTime $date,
				) use (
					$assetA,
					$assetAStartRecord,
					$assetAEndRecord,
					$assetB,
					$assetBStartRecord,
					$assetBEndRecord,
					$dateFrom,
				) {
					if ($stockAsset === $assetA) {
						return $date->format('Y-m-d') === $dateFrom->format(
							'Y-m-d',
						)
							? $assetAStartRecord
							: $assetAEndRecord;
					}

					if ($stockAsset === $assetB) {
						return $date->format('Y-m-d') === $dateFrom->format(
							'Y-m-d',
						)
							? $assetBStartRecord
							: $assetBEndRecord;
					}

					return null;
				},
			);

		$dividendRecord = self::createMockWithIgnoreMethods(StockAssetDividendRecord::class);
		$stockAssetDividend = self::createMockWithIgnoreMethods(StockAssetDividend::class);
		$dividendAsset = self::createMockWithIgnoreMethods(StockAsset::class);
		$paymentDate = new ImmutableDateTime('2026-04-15 00:00:00');
		$dividendAsset->shouldReceive('getTicker')->andReturn('DIV');
		$dividendAsset->shouldReceive('getName')->andReturn('Dividend Corp.');
		$stockAssetDividend->shouldReceive('getStockAsset')->andReturn($dividendAsset);
		$stockAssetDividend->shouldReceive('getPaymentDate')->andReturn($paymentDate);
		$dividendRecord->shouldReceive('getStockAssetDividend')->andReturn($stockAssetDividend);
		$dividendRecord->shouldReceive('getDividendTax')->andReturn(10.0);
		$dividendRecord->shouldReceive('getTotalAmount')->andReturn(50.0);
		$dividendRecord->shouldReceive('getCurrency')->andReturn(CurrencyEnum::CZK);

		$this->stockAssetDividendRecordRepository->shouldReceive('findBetweenDates')
			->once()
			->with($dateFrom, $dateTo)
			->andReturn([$dividendRecord]);

		$portfolioGoal = self::createMockWithIgnoreMethods(PortfolioGoal::class);
		$portfolioGoal->shouldReceive('getStartDate')->andReturn(new ImmutableDateTime('2026-01-01 00:00:00'));
		$portfolioGoal->shouldReceive('getEndDate')->andReturn(new ImmutableDateTime('2026-12-31 23:59:59'));
		$portfolioGoal->shouldReceive('getStatistics')->andReturn([
			'2026-04-01' => 100.0,
			'2026-04-30' => 150.0,
		]);
		$portfolioGoal->shouldReceive('getType')->andReturn(PortfolioGoalTypeEnum::TOTAL_INVESTED_AMOUNT);
		$portfolioGoal->shouldReceive('getGoal')->andReturn(200.0);
		$portfolioGoal->shouldReceive('getValueAtStart')->andReturn(100.0);
		$portfolioGoal->shouldReceive('getValueAtEnd')->andReturn(150.0);
		$portfolioGoal->shouldReceive('getCurrentValue')->andReturn(150.0);

		$this->portfolioGoalRepository->shouldReceive('findAll')
			->once()
			->andReturn([$portfolioGoal]);

		$this->currencyConversionFacade->shouldReceive('convertSimpleValue')
			->andReturnUsing(static fn (float $price): float => $price);

		$this->portfolioReportPromptGenerator->shouldReceive('generate')
			->once()
			->with(
				$portfolioReport,
				Mockery::on(
					static fn (array $payload): bool => ($payload['portfolioSummary']['portfolioValueStartCzk'] ?? null) === 1000.0
							&& ($payload['portfolioSummary']['portfolioValueEndCzk'] ?? null) === 1250.0,
				),
			)
			->andReturn('prompt');

		$result = $this->portfolioReportGenerator->generate($portfolioReport, $now);

		$this->assertSame(1000.0, $result->getPortfolioValueStartCzk());
		$this->assertSame(1250.0, $result->getPortfolioValueEndCzk());
		$this->assertSame(800.0, $result->getInvestedAmountStartCzk());
		$this->assertSame(900.0, $result->getInvestedAmountEndCzk());
		$this->assertSame(45.0, $result->getDividendsTotalCzk());
		$this->assertCount(1, $result->getGoalProgressItems());
		$this->assertSame('prompt', $result->getAiPrompt());

		$priceWinnerTickers = [];
		$priceLoserTickers = [];
		foreach ($result->getAssetPerformances() as $assetPerformance) {
			if (
				$assetPerformance->getRankingType() === PortfolioReportAssetRankingTypeEnum::PRICE
				&& $assetPerformance->getDirection() === PortfolioReportAssetRankingDirectionEnum::WINNER
			) {
				$priceWinnerTickers[] = $assetPerformance->getTicker();
			}

			if (
				$assetPerformance->getRankingType() === PortfolioReportAssetRankingTypeEnum::PRICE
				&& $assetPerformance->getDirection() === PortfolioReportAssetRankingDirectionEnum::LOSER
			) {
				$priceLoserTickers[] = $assetPerformance->getTicker();
			}
		}

		$this->assertContains('AAA', $priceWinnerTickers);
		$this->assertContains('BBB', $priceLoserTickers);
	}

	/**
	 * @return array{StockAsset, StockPosition, StockAssetPriceRecord, StockAssetPriceRecord}
	 */
	private function createPositionPriceScenario(
		string $ticker,
		string $name,
		float $priceStart,
		float $priceEnd,
		ImmutableDateTime $dateFrom,
		ImmutableDateTime $dateTo,
	): array
	{
		$stockAsset = self::createMockWithIgnoreMethods(StockAsset::class);
		$stockAsset->shouldReceive('getTicker')->andReturn($ticker);
		$stockAsset->shouldReceive('getName')->andReturn($name);
		$stockAsset->shouldReceive('getCurrency')->andReturn(CurrencyEnum::CZK);
		$stockAsset->shouldReceive('getAssetCurrentPrice')->andReturn(
			new AssetPrice($stockAsset, $priceEnd, CurrencyEnum::CZK),
		);

		$stockPosition = self::createMockWithIgnoreMethods(StockPosition::class);
		$stockPosition->shouldReceive('getOrderDate')->andReturn(new ImmutableDateTime('2026-03-15 00:00:00'));
		$stockPosition->shouldReceive('getStockClosedPosition')->andReturn(null);
		$stockPosition->shouldReceive('getAsset')->andReturn($stockAsset);
		$stockPosition->shouldReceive('getOrderPiecesCount')->andReturn(1);
		$stockPosition->shouldReceive('getPricePerPiece')->andReturn(
			new AssetPrice($stockAsset, $priceStart, CurrencyEnum::CZK),
		);

		$startRecord = self::createMockWithIgnoreMethods(StockAssetPriceRecord::class);
		$startRecord->shouldReceive('getPrice')->andReturn($priceStart);
		$startRecord->shouldReceive('getDate')->andReturn($dateFrom);

		$endRecord = self::createMockWithIgnoreMethods(StockAssetPriceRecord::class);
		$endRecord->shouldReceive('getPrice')->andReturn($priceEnd);
		$endRecord->shouldReceive('getDate')->andReturn($dateTo);

		return [$stockAsset, $stockPosition, $startRecord, $endRecord];
	}

}
