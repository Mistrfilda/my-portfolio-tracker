<?php

declare(strict_types = 1);

namespace App\Test\Unit\Statistic\PeriodStatistic;

use App\Crypto\Asset\CryptoAssetRepository;
use App\Crypto\Position\Closed\CryptoClosedPositionRepository;
use App\Crypto\Price\CryptoAssetPriceRecordRepository;
use App\Currency\CurrencyConversionFacade;
use App\Dashboard\DashboardValueGroupEnum;
use App\Portu\Asset\PortuAssetRepository;
use App\Portu\Price\PortuAssetPriceRecordRepository;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticChartPointDTO;
use App\Statistic\PeriodStatistic\PortfolioPeriodStatistic;
use App\Statistic\PeriodStatistic\PortfolioPeriodStatisticBuilder;
use App\Statistic\PeriodStatistic\PortfolioPeriodStatisticUnableToBuildException;
use App\Statistic\PortfolioStatistic;
use App\Statistic\PortfolioStatisticControlTypeEnum;
use App\Statistic\PortfolioStatisticRecord;
use App\Statistic\PortfolioStatisticRecordRepository;
use App\Statistic\PortolioStatisticType;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\Record\StockAssetDividendRecordRepository;
use App\Stock\Position\Closed\StockClosedPositionRepository;
use App\Stock\Price\StockAssetPriceRecordRepository;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class PortfolioPeriodStatisticBuilderTest extends TestCase
{

	public function testBuildThrowsWhenPeriodDoesNotContainSnapshots(): void
	{
		$recordRepository = $this->createMock(PortfolioStatisticRecordRepository::class);
		$recordRepository->expects(self::once())
			->method('findFirstBetweenDates')
			->willReturn(null);
		$recordRepository->expects(self::once())
			->method('findLastBetweenDates')
			->willReturn(null);

		$this->expectException(PortfolioPeriodStatisticUnableToBuildException::class);
		$this->expectExceptionMessage('At least two portfolio statistic snapshots are required');

		$this->createBuilder($recordRepository)->build($this->createReport());
	}

	public function testBuildThrowsWhenSnapshotsAreFromSameDay(): void
	{
		$startRecord = $this->createRecord('2026-01-10 08:00:00', 100.0, 120.0);
		$endRecord = $this->createRecord('2026-01-10 20:00:00', 110.0, 130.0);
		$recordRepository = $this->createStub(PortfolioStatisticRecordRepository::class);
		$recordRepository->method('findFirstBetweenDates')->willReturn($startRecord);
		$recordRepository->method('findLastBetweenDates')->willReturn($endRecord);

		$this->expectException(PortfolioPeriodStatisticUnableToBuildException::class);
		$this->expectExceptionMessage('must cover at least two different days');

		$this->createBuilder($recordRepository)->build($this->createReport());
	}

	public function testBuildCalculatesSummaryAndChartFromEffectiveSnapshots(): void
	{
		$startRecord = $this->createRecord('2026-01-02 10:00:00', 100.0, 120.0);
		$endRecord = $this->createRecord('2026-01-30 20:00:00', 150.0, 190.0);
		$recordRepository = $this->createStub(PortfolioStatisticRecordRepository::class);
		$recordRepository->method('findFirstBetweenDates')->willReturn($startRecord);
		$recordRepository->method('findLastBetweenDates')->willReturn($endRecord);
		$recordRepository->method('findDailyInvestedCzkBetweenDates')->willReturn([
			['date' => $startRecord->getCreatedAt(), 'amount' => 100.0],
			['date' => $endRecord->getCreatedAt(), 'amount' => 150.0],
		]);
		$recordRepository->method('findBetweenDates')->willReturn([$startRecord, $endRecord]);

		$result = $this->createBuilder($recordRepository)->build($this->createReport());

		self::assertSame('2026-01-02 10:00:00', $result->effectiveStartAt->format('Y-m-d H:i:s'));
		self::assertSame('2026-01-30 20:00:00', $result->effectiveEndAt->format('Y-m-d H:i:s'));
		self::assertSame(100.0, $result->summary->investedAtStart);
		self::assertSame(150.0, $result->summary->investedAtEnd);
		self::assertSame(50.0, $result->summary->investedDifference);
		self::assertSame(120.0, $result->summary->valueAtStart);
		self::assertSame(190.0, $result->summary->valueAtEnd);
		self::assertSame(70.0, $result->summary->valueDifference);
		self::assertEqualsWithDelta(58.3333, $result->summary->valueDifferencePercentage, 0.0001);
		self::assertSame(20.0, $result->summary->periodProfit);
		self::assertSame(20.0, $result->summary->totalPeriodProfit);
		self::assertEqualsWithDelta(11.7647, $result->summary->timeWeightedReturn, 0.0001);
		self::assertFalse($result->summary->partial);
		self::assertSame([
			'Začátek byl posunut na první dostupný snapshot 2026-01-02.',
			'Konec byl posunut na poslední dostupný snapshot 2026-01-30.',
		], $result->summary->warnings);
		self::assertSame([], $result->assetSection->assets);
		self::assertSame(0, $result->dividendSection->count);
		self::assertSame(['2026-01-02', '2026-01-30'], array_map(
			static fn (PortfolioPeriodStatisticChartPointDTO $point): string => $point->label,
			$result->chartSection->portfolioValues,
		));
		self::assertSame([120.0, 190.0], array_map(
			static fn (PortfolioPeriodStatisticChartPointDTO $point): float => $point->value,
			$result->chartSection->portfolioValues,
		));
		self::assertSame([100.0, 150.0], array_map(
			static fn (PortfolioPeriodStatisticChartPointDTO $point): float => $point->value,
			$result->chartSection->investedValues,
		));
	}

	public function testBuildThrowsWhenSnapshotMissesRequiredValue(): void
	{
		$startRecord = $this->createRecord('2026-01-02 10:00:00', 100.0, null);
		$endRecord = $this->createRecord('2026-01-30 20:00:00', 150.0, 190.0);
		$recordRepository = $this->createStub(PortfolioStatisticRecordRepository::class);
		$recordRepository->method('findFirstBetweenDates')->willReturn($startRecord);
		$recordRepository->method('findLastBetweenDates')->willReturn($endRecord);

		$this->expectException(PortfolioPeriodStatisticUnableToBuildException::class);
		$this->expectExceptionMessage('missing required value total_value_in_czk');

		$this->createBuilder($recordRepository)->build($this->createReport());
	}

	private function createBuilder(
		PortfolioStatisticRecordRepository $recordRepository,
	): PortfolioPeriodStatisticBuilder
	{
		return new PortfolioPeriodStatisticBuilder(
			$recordRepository,
			$this->createStub(StockAssetDividendRecordRepository::class),
			$this->createStub(StockClosedPositionRepository::class),
			$this->createStub(CryptoClosedPositionRepository::class),
			$this->createStub(CurrencyConversionFacade::class),
			$this->createStub(StockAssetRepository::class),
			$this->createStub(CryptoAssetRepository::class),
			$this->createStub(PortuAssetRepository::class),
			$this->createStub(StockAssetPriceRecordRepository::class),
			$this->createStub(CryptoAssetPriceRecordRepository::class),
			$this->createStub(PortuAssetPriceRecordRepository::class),
		);
	}

	private function createReport(): PortfolioPeriodStatistic
	{
		return new PortfolioPeriodStatistic(
			new ImmutableDateTime('2026-01-01 00:00:00'),
			new ImmutableDateTime('2026-01-31 23:59:59'),
			new ImmutableDateTime('2026-02-01 12:00:00'),
		);
	}

	private function createRecord(
		string $date,
		float $invested,
		float|null $value,
	): PortfolioStatisticRecord
	{
		$now = new ImmutableDateTime($date);
		$record = new PortfolioStatisticRecord($now);
		$this->addStatistic($record, $now, PortolioStatisticType::TOTAL_INVESTED_IN_CZK, $invested);
		if ($value !== null) {
			$this->addStatistic($record, $now, PortolioStatisticType::TOTAL_VALUE_IN_CZK, $value);
		}

		return $record;
	}

	private function addStatistic(
		PortfolioStatisticRecord $record,
		ImmutableDateTime $now,
		PortolioStatisticType $type,
		float $value,
	): void
	{
		$record->getPortfolioStatistics()->add(new PortfolioStatistic(
			$record,
			$now,
			DashboardValueGroupEnum::TOTAL_VALUES,
			$type->format(),
			(string) $value,
			'blue',
			null,
			null,
			$type,
			PortfolioStatisticControlTypeEnum::SIMPLE_VALUE,
			null,
		));
	}

}
