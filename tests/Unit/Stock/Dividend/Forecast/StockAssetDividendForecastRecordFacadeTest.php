<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Dividend\Forecast;

use App\Asset\Price\SummaryPrice;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\Forecast\StockAssetDividendForecast;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRecord;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRecordFacade;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRecordRepository;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRepository;
use App\Stock\Dividend\Forecast\StockAssetDividendTrendEnum;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Dividend\StockAssetDividendRepository;
use App\Stock\Dividend\StockAssetDividendTypeEnum;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class StockAssetDividendForecastRecordFacadeTest extends TestCase
{

	private StockAssetRepository|MockInterface $stockAssetRepository;

	private StockAssetDividendRepository|MockInterface $stockAssetDividendRepository;

	private StockAssetDividendForecastRepository|MockInterface $stockAssetDividendForecastRepository;

	private StockAssetDividendForecastRecordRepository|MockInterface $stockAssetDividendForecastRecordRepository;

	private EntityManagerInterface|MockInterface $entityManager;

	private DatetimeFactory|MockInterface $datetimeFactory;

	private LoggerInterface|MockInterface $logger;

	private StockAssetDividendForecastRecordFacade $facade;

	private UuidInterface $forecastId;

	private ImmutableDateTime $now;

	protected function setUp(): void
	{
		parent::setUp();

		$this->stockAssetRepository = Mockery::mock(StockAssetRepository::class);
		$this->stockAssetDividendRepository = Mockery::mock(StockAssetDividendRepository::class);
		$this->stockAssetDividendForecastRepository = Mockery::mock(StockAssetDividendForecastRepository::class);
		$this->stockAssetDividendForecastRecordRepository = Mockery::mock(
			StockAssetDividendForecastRecordRepository::class,
		);
		$this->entityManager = Mockery::mock(EntityManagerInterface::class);
		$this->datetimeFactory = Mockery::mock(DatetimeFactory::class);
		$this->logger = Mockery::mock(LoggerInterface::class);

		$this->facade = new StockAssetDividendForecastRecordFacade(
			$this->stockAssetRepository,
			$this->stockAssetDividendRepository,
			$this->stockAssetDividendForecastRepository,
			$this->stockAssetDividendForecastRecordRepository,
			$this->entityManager,
			$this->datetimeFactory,
			$this->logger,
		);

		$this->forecastId = Uuid::uuid4();
		$this->now = new ImmutableDateTime('2024-01-15 10:00:00');

		$this->datetimeFactory
			->shouldReceive('createNow')
			->andReturn($this->now);
	}

	public function testRecalculateWithNoStockAssets(): void
	{
		$forecast = Mockery::mock(StockAssetDividendForecast::class);
		$forecast->shouldReceive('getId')->andReturn($this->forecastId);
		$forecast->shouldReceive('getForYear')->andReturn(2024);
		$forecast->shouldReceive('getTrend')->andReturn(StockAssetDividendTrendEnum::NEUTRAL);
		$forecast->shouldReceive('recalculated')->once()->with($this->now);

		$this->stockAssetDividendForecastRepository
			->shouldReceive('getById')
			->with($this->forecastId)
			->once()
			->andReturn($forecast);

		$this->stockAssetDividendForecastRecordRepository
			->shouldReceive('findByStockAssetDividendForecast')
			->with($this->forecastId)
			->once()
			->andReturn([]);

		$this->stockAssetRepository
			->shouldReceive('findAll')
			->once()
			->andReturn([]);

		$this->entityManager->shouldReceive('flush')->twice();

		$this->facade->recalculate($this->forecastId);

		$this->assertTrue(true);
	}

	public function testRecalculateWithStockAssetWithoutOpenPositions(): void
	{
		$forecast = Mockery::mock(StockAssetDividendForecast::class);
		$forecast->shouldReceive('getId')->andReturn($this->forecastId);
		$forecast->shouldReceive('getForYear')->andReturn(2024);
		$forecast->shouldReceive('getTrend')->andReturn(StockAssetDividendTrendEnum::NEUTRAL);
		$forecast->shouldReceive('recalculated')->once()->with($this->now);

		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('hasOpenPositions')->andReturn(false);
		$stockAsset->shouldReceive('getId')->andReturn(Uuid::uuid4());

		$this->stockAssetDividendForecastRepository
			->shouldReceive('getById')
			->with($this->forecastId)
			->once()
			->andReturn($forecast);

		$this->stockAssetDividendForecastRecordRepository
			->shouldReceive('findByStockAssetDividendForecast')
			->with($this->forecastId)
			->once()
			->andReturn([]);

		$this->stockAssetRepository
			->shouldReceive('findAll')
			->once()
			->andReturn([$stockAsset]);

		$this->entityManager->shouldReceive('flush')->twice();

		$this->facade->recalculate($this->forecastId);

		$this->assertTrue(true);
	}

	public function testRecalculateWithStockAssetThatDoesNotPayDividends(): void
	{
		$forecast = Mockery::mock(StockAssetDividendForecast::class);
		$forecast->shouldReceive('getId')->andReturn($this->forecastId);
		$forecast->shouldReceive('getForYear')->andReturn(2024);
		$forecast->shouldReceive('getTrend')->andReturn(StockAssetDividendTrendEnum::NEUTRAL);
		$forecast->shouldReceive('recalculated')->once()->with($this->now);

		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('hasOpenPositions')->andReturn(true);
		$stockAsset->shouldReceive('doesPaysDividends')->andReturn(false);
		$stockAsset->shouldReceive('getId')->andReturn(Uuid::uuid4());

		$this->stockAssetDividendForecastRepository
			->shouldReceive('getById')
			->with($this->forecastId)
			->once()
			->andReturn($forecast);

		$this->stockAssetDividendForecastRecordRepository
			->shouldReceive('findByStockAssetDividendForecast')
			->with($this->forecastId)
			->once()
			->andReturn([]);

		$this->stockAssetRepository
			->shouldReceive('findAll')
			->once()
			->andReturn([$stockAsset]);

		$this->entityManager->shouldReceive('flush')->twice();

		$this->facade->recalculate($this->forecastId);

		$this->assertTrue(true);
	}

	public function testRecalculateAll(): void
	{
		$forecast1 = Mockery::mock(StockAssetDividendForecast::class);
		$forecast1->shouldReceive('getId')->andReturn(Uuid::uuid4());
		$forecast1->shouldReceive('getForYear')->andReturn(2024);
		$forecast1->shouldReceive('recalculated')->once();

		$forecast2 = Mockery::mock(StockAssetDividendForecast::class);
		$forecast2->shouldReceive('getId')->andReturn(Uuid::uuid4());
		$forecast2->shouldReceive('getForYear')->andReturn(2024);
		$forecast2->shouldReceive('recalculated')->once();

		$this->logger->shouldReceive('debug')->with('Recalculating all forecasts')->once();
		$this->logger->shouldReceive('debug')->with('All forecasts recalculated')->once();

		$this->stockAssetDividendForecastRepository
			->shouldReceive('findAllActive')
			->with(2024)
			->once()
			->andReturn([$forecast1, $forecast2]);

		$this->stockAssetDividendForecastRepository
			->shouldReceive('getById')
			->twice()
			->andReturn($forecast1, $forecast2);

		$this->stockAssetDividendForecastRecordRepository
			->shouldReceive('findByStockAssetDividendForecast')
			->twice()
			->andReturn([]);

		$this->stockAssetRepository
			->shouldReceive('findAll')
			->twice()
			->andReturn([]);

		$this->entityManager->shouldReceive('flush')->times(4);

		$this->facade->recalculateAll();

		$this->assertTrue(true);
	}

	public function testRecalculateSkipsStockAssetWhenGetLastDividendReturnsNull(): void
	{
		$forecast = Mockery::mock(StockAssetDividendForecast::class);
		$forecast->shouldReceive('getId')->andReturn($this->forecastId);
		$forecast->shouldReceive('getForYear')->andReturn(2024);
		$forecast->shouldReceive('getTrend')->andReturn(StockAssetDividendTrendEnum::NEUTRAL);
		$forecast->shouldReceive('recalculated')->once()->with($this->now);

		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('hasOpenPositions')->andReturn(true);
		$stockAsset->shouldReceive('doesPaysDividends')->andReturn(true);
		$stockAsset->shouldReceive('getId')->andReturn(Uuid::uuid4());
		$stockAsset->shouldReceive('getCurrency')->andReturn(CurrencyEnum::USD);
		$stockAsset->shouldReceive('getTotalPiecesHeld')->andReturn(1);

		$this->stockAssetDividendForecastRepository
			->shouldReceive('getById')
			->with($this->forecastId)
			->once()
			->andReturn($forecast);

		$this->stockAssetDividendForecastRecordRepository
			->shouldReceive('findByStockAssetDividendForecast')
			->with($this->forecastId)
			->once()
			->andReturn([]);

		$this->stockAssetRepository
			->shouldReceive('findAll')
			->once()
			->andReturn([$stockAsset]);

		$this->stockAssetDividendRepository
			->shouldReceive('findByStockAssetForYear')
			->twice()
			->andReturn([], []);

		$this->stockAssetDividendRepository
			->shouldReceive('getLastDividend')
			->with($stockAsset, StockAssetDividendTypeEnum::REGULAR)
			->once()
			->andReturn(null);

		$this->entityManager->shouldReceive('persist')->never();
		$this->entityManager->shouldReceive('remove')->never();
		$this->entityManager->shouldReceive('flush')->twice();

		$this->facade->recalculate($this->forecastId);

		$this->assertTrue(true);
	}

	public function testRecalculateUsesCustomDividendWhenSet(): void
	{
		$stockAssetId = Uuid::uuid4();

		$forecast = Mockery::mock(StockAssetDividendForecast::class);
		$forecast->shouldReceive('getId')->andReturn($this->forecastId);
		$forecast->shouldReceive('getForYear')->andReturn(2024);
		$forecast->shouldReceive('getTrend')->andReturn(StockAssetDividendTrendEnum::NEUTRAL);
		$forecast->shouldReceive('recalculated')->once()->with($this->now);

		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('hasOpenPositions')->andReturn(true);
		$stockAsset->shouldReceive('doesPaysDividends')->andReturn(true);
		$stockAsset->shouldReceive('getId')->andReturn($stockAssetId);
		$stockAsset->shouldReceive('getCurrency')->andReturn(CurrencyEnum::USD);
		$stockAsset->shouldReceive('getTotalPiecesHeld')->andReturn(10);

		// Existující záznam s custom dividendou
		$existingRecord = Mockery::mock(StockAssetDividendForecastRecord::class);
		$existingRecord->shouldReceive('getStockAsset')->andReturn($stockAsset);
		$existingRecord->shouldReceive('getCustomDividendUsedForCalculation')->andReturn(2.5);
		$existingRecord->shouldReceive('getExpectedSpecialDividendThisYearPerStock')->andReturn(null);

		$existingRecord->shouldReceive('recalculate')->once()->with(
			[1],
			1.0,
			[1, 4, 7, 10],
			10,
			1.0,
			1.0,
			7.5,
			2.5,
			0.0,
		);

		$this->stockAssetDividendForecastRepository
			->shouldReceive('getById')
			->with($this->forecastId)
			->once()
			->andReturn($forecast);

		$this->stockAssetDividendForecastRecordRepository
			->shouldReceive('findByStockAssetDividendForecast')
			->with($this->forecastId)
			->once()
			->andReturn([$existingRecord]);

		$this->stockAssetRepository
			->shouldReceive('findAll')
			->once()
			->andReturn([$stockAsset]);

		// Previous year dividends - rok 2023 (forYear - 1)
		$previousDividend1 = $this->createDividendMock(1, 1.0, StockAssetDividendTypeEnum::REGULAR);
		$previousDividend2 = $this->createDividendMock(4, 1.0, StockAssetDividendTypeEnum::REGULAR);
		$previousDividend3 = $this->createDividendMock(7, 1.0, StockAssetDividendTypeEnum::REGULAR);
		$previousDividend4 = $this->createDividendMock(10, 1.0, StockAssetDividendTypeEnum::REGULAR);

		$this->stockAssetDividendRepository
			->shouldReceive('findByStockAssetForYear')
			->with($stockAsset, 2023) // Opraveno z 2022 na 2023
			->once()
			->andReturn([$previousDividend1, $previousDividend2, $previousDividend3, $previousDividend4]);

		// Current year
		$receivedDividend = $this->createDividendMock(1, 1.0, StockAssetDividendTypeEnum::REGULAR);

		$this->stockAssetDividendRepository
			->shouldReceive('findByStockAssetForYear')
			->with($stockAsset, 2024)
			->once()
			->andReturn([$receivedDividend]);

		$this->entityManager->shouldReceive('flush')->twice();

		$this->facade->recalculate($this->forecastId);

		$this->assertTrue(true);
	}

	public function testRecalculateAddsExpectedSpecialDividend(): void
	{
		$stockAssetId = Uuid::uuid4();

		$forecast = Mockery::mock(StockAssetDividendForecast::class);
		$forecast->shouldReceive('getId')->andReturn($this->forecastId);
		$forecast->shouldReceive('getForYear')->andReturn(2024);
		$forecast->shouldReceive('getTrend')->andReturn(StockAssetDividendTrendEnum::NEUTRAL);
		$forecast->shouldReceive('recalculated')->once()->with($this->now);

		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('hasOpenPositions')->andReturn(true);
		$stockAsset->shouldReceive('doesPaysDividends')->andReturn(true);
		$stockAsset->shouldReceive('getId')->andReturn($stockAssetId);
		$stockAsset->shouldReceive('getCurrency')->andReturn(CurrencyEnum::USD);
		$stockAsset->shouldReceive('getTotalPiecesHeld')->andReturn(10);

		$existingRecord = Mockery::mock(StockAssetDividendForecastRecord::class);
		$existingRecord->shouldReceive('getStockAsset')->andReturn($stockAsset);
		$existingRecord->shouldReceive('getCustomDividendUsedForCalculation')->andReturn(null);
		$existingRecord->shouldReceive('getExpectedSpecialDividendThisYearPerStock')->andReturn(5.0);

		$existingRecord->shouldReceive('recalculate')->once()->with(
			[1],
			1.0,
			[1, 4, 7, 10],
			10,
			1.0,
			1.0,
			8.0,
			null,
			0.0,
		);

		$this->stockAssetDividendForecastRepository
			->shouldReceive('getById')
			->with($this->forecastId)
			->once()
			->andReturn($forecast);

		$this->stockAssetDividendForecastRecordRepository
			->shouldReceive('findByStockAssetDividendForecast')
			->with($this->forecastId)
			->once()
			->andReturn([$existingRecord]);

		$this->stockAssetRepository
			->shouldReceive('findAll')
			->once()
			->andReturn([$stockAsset]);

		$previousDividend1 = $this->createDividendMock(1, 1.0, StockAssetDividendTypeEnum::REGULAR);
		$previousDividend2 = $this->createDividendMock(4, 1.0, StockAssetDividendTypeEnum::REGULAR);
		$previousDividend3 = $this->createDividendMock(7, 1.0, StockAssetDividendTypeEnum::REGULAR);
		$previousDividend4 = $this->createDividendMock(10, 1.0, StockAssetDividendTypeEnum::REGULAR);

		$this->stockAssetDividendRepository
			->shouldReceive('findByStockAssetForYear')
			->with($stockAsset, 2023) // Opraveno z 2022 na 2023
			->once()
			->andReturn([$previousDividend1, $previousDividend2, $previousDividend3, $previousDividend4]);

		$receivedDividend = $this->createDividendMock(1, 1.0, StockAssetDividendTypeEnum::REGULAR);

		$this->stockAssetDividendRepository
			->shouldReceive('findByStockAssetForYear')
			->with($stockAsset, 2024)
			->once()
			->andReturn([$receivedDividend]);

		$this->entityManager->shouldReceive('flush')->twice();

		$this->facade->recalculate($this->forecastId);

		$this->assertTrue(true);
	}

	public function testRecalculateWithBothCustomDividendAndSpecialDividend(): void
	{
		$stockAssetId = Uuid::uuid4();

		$forecast = Mockery::mock(StockAssetDividendForecast::class);
		$forecast->shouldReceive('getId')->andReturn($this->forecastId);
		$forecast->shouldReceive('getForYear')->andReturn(2024);
		$forecast->shouldReceive('getTrend')->andReturn(StockAssetDividendTrendEnum::NEUTRAL);
		$forecast->shouldReceive('recalculated')->once()->with($this->now);

		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('hasOpenPositions')->andReturn(true);
		$stockAsset->shouldReceive('doesPaysDividends')->andReturn(true);
		$stockAsset->shouldReceive('getId')->andReturn($stockAssetId);
		$stockAsset->shouldReceive('getCurrency')->andReturn(CurrencyEnum::USD);
		$stockAsset->shouldReceive('getTotalPiecesHeld')->andReturn(10);

		$existingRecord = Mockery::mock(StockAssetDividendForecastRecord::class);
		$existingRecord->shouldReceive('getStockAsset')->andReturn($stockAsset);
		$existingRecord->shouldReceive('getCustomDividendUsedForCalculation')->andReturn(2.0);
		$existingRecord->shouldReceive('getExpectedSpecialDividendThisYearPerStock')->andReturn(3.0);

		// Očekávaná speciální dividenda je 3.0, už obdržená je 1.0, takže zbývá 2.0
		// expectedDividendPerStock = (3 zbývající regular dividendy * 2.0 custom) + 2.0 zbývající special = 8.0
		$existingRecord->shouldReceive('recalculate')->once()->with(
			[1],
			1.0,
			[1, 4, 7, 10],
			10,
			1.0,
			1.0,
			8.0, // Změněno z 9.0 na 8.0 (3*2 + (3-1))
			2.0,
			1.0, // Změněno z 0.0 na 1.0 - obdržená speciální dividenda
		);

		$this->stockAssetDividendForecastRepository
			->shouldReceive('getById')
			->with($this->forecastId)
			->once()
			->andReturn($forecast);

		$this->stockAssetDividendForecastRecordRepository
			->shouldReceive('findByStockAssetDividendForecast')
			->with($this->forecastId)
			->once()
			->andReturn([$existingRecord]);

		$this->stockAssetRepository
			->shouldReceive('findAll')
			->once()
			->andReturn([$stockAsset]);

		$previousDividend1 = $this->createDividendMock(1, 1.0, StockAssetDividendTypeEnum::REGULAR);
		$previousDividend2 = $this->createDividendMock(4, 1.0, StockAssetDividendTypeEnum::REGULAR);
		$previousDividend3 = $this->createDividendMock(7, 1.0, StockAssetDividendTypeEnum::REGULAR);
		$previousDividend4 = $this->createDividendMock(10, 1.0, StockAssetDividendTypeEnum::REGULAR);

		$this->stockAssetDividendRepository
			->shouldReceive('findByStockAssetForYear')
			->with($stockAsset, 2023)
			->once()
			->andReturn([$previousDividend1, $previousDividend2, $previousDividend3, $previousDividend4]);

		$receivedDividend = $this->createDividendMock(1, 1.0, StockAssetDividendTypeEnum::REGULAR);
		$receivedSpecialDividend = $this->createDividendMock(2, 1.0, StockAssetDividendTypeEnum::SPECIAL);

		$this->stockAssetDividendRepository
			->shouldReceive('findByStockAssetForYear')
			->with($stockAsset, 2024)
			->once()
			->andReturn([$receivedDividend, $receivedSpecialDividend]);

		$this->entityManager->shouldReceive('flush')->twice();

		$this->facade->recalculate($this->forecastId);

		$this->assertTrue(true);
	}

	public function testUpdateCustomValuesForRecord(): void
	{
		$recordId = Uuid::uuid4();
		$customDividend = 1.5;
		$specialDividend = 2.0;

		$forecast = Mockery::mock(StockAssetDividendForecast::class);
		$forecast->shouldReceive('recalculatingPending')->once();

		$record = Mockery::mock(StockAssetDividendForecastRecord::class);
		$record->shouldReceive('setCustomValues')
			->once()
			->with($customDividend, $specialDividend);
		$record->shouldReceive('getStockAssetDividendForecast')
			->once()
			->andReturn($forecast);

		$this->stockAssetDividendForecastRecordRepository
			->shouldReceive('getById')
			->with($recordId)
			->once()
			->andReturn($record);

		$this->entityManager->shouldReceive('flush')->once();

		$this->facade->updateCustomValuesForRecord($recordId, $customDividend, $specialDividend);

		$this->assertTrue(true);
	}

	public function testUpdateCustomValuesForRecordWithNullValues(): void
	{
		$recordId = Uuid::uuid4();

		$forecast = Mockery::mock(StockAssetDividendForecast::class);
		$forecast->shouldReceive('recalculatingPending')->once();

		$record = Mockery::mock(StockAssetDividendForecastRecord::class);
		$record->shouldReceive('setCustomValues')
			->once()
			->with(null, null);
		$record->shouldReceive('getStockAssetDividendForecast')
			->once()
			->andReturn($forecast);

		$this->stockAssetDividendForecastRecordRepository
			->shouldReceive('getById')
			->with($recordId)
			->once()
			->andReturn($record);

		$this->entityManager->shouldReceive('flush')->once();

		$this->facade->updateCustomValuesForRecord($recordId, null, null);

		$this->assertTrue(true);
	}

	private function createDividendMock(
		int $month,
		float $amount,
		StockAssetDividendTypeEnum $type,
	): StockAssetDividend|MockInterface
	{
		$exDate = Mockery::mock(ImmutableDateTime::class);
		$exDate->shouldReceive('getMonth')->andReturn($month);

		$summaryPrice = new SummaryPrice(CurrencyEnum::USD, $amount);

		$dividend = Mockery::mock(StockAssetDividend::class);
		$dividend->shouldReceive('getExDate')->andReturn($exDate);
		$dividend->shouldReceive('getDividendType')->andReturn($type);
		$dividend->shouldReceive('getSummaryPrice')->andReturn($summaryPrice);

		return $dividend;
	}

	public function testRecalculateWhenReceivedSpecialDividendExceedsExpected(): void
	{
		$stockAssetId = Uuid::uuid4();

		$forecast = Mockery::mock(StockAssetDividendForecast::class);
		$forecast->shouldReceive('getId')->andReturn($this->forecastId);
		$forecast->shouldReceive('getForYear')->andReturn(2024);
		$forecast->shouldReceive('getTrend')->andReturn(StockAssetDividendTrendEnum::NEUTRAL);
		$forecast->shouldReceive('recalculated')->once()->with($this->now);

		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('hasOpenPositions')->andReturn(true);
		$stockAsset->shouldReceive('doesPaysDividends')->andReturn(true);
		$stockAsset->shouldReceive('getId')->andReturn($stockAssetId);
		$stockAsset->shouldReceive('getCurrency')->andReturn(CurrencyEnum::USD);
		$stockAsset->shouldReceive('getTotalPiecesHeld')->andReturn(10);

		$existingRecord = Mockery::mock(StockAssetDividendForecastRecord::class);
		$existingRecord->shouldReceive('getStockAsset')->andReturn($stockAsset);
		$existingRecord->shouldReceive('getCustomDividendUsedForCalculation')->andReturn(null);
		// Očekávaná speciální dividenda je 3.0, ale obdržená je 5.0
		$existingRecord->shouldReceive('getExpectedSpecialDividendThisYearPerStock')->andReturn(3.0);

		// Speciální dividenda se nepřičte, protože obdržená (5.0) > očekávaná (3.0)
		// expectedDividendPerStock = 3 zbývající regular * 1.0 = 3.0 (bez speciální)
		$existingRecord->shouldReceive('recalculate')->once()->with(
			[1],
			1.0,
			[1, 4, 7, 10],
			10,
			1.0,
			1.0,
			3.0, // Pouze regular dividendy, speciální už překročena
			null,
			5.0, // Obdržená speciální dividenda
		);

		$this->stockAssetDividendForecastRepository
			->shouldReceive('getById')
			->with($this->forecastId)
			->once()
			->andReturn($forecast);

		$this->stockAssetDividendForecastRecordRepository
			->shouldReceive('findByStockAssetDividendForecast')
			->with($this->forecastId)
			->once()
			->andReturn([$existingRecord]);

		$this->stockAssetRepository
			->shouldReceive('findAll')
			->once()
			->andReturn([$stockAsset]);

		$previousDividend1 = $this->createDividendMock(1, 1.0, StockAssetDividendTypeEnum::REGULAR);
		$previousDividend2 = $this->createDividendMock(4, 1.0, StockAssetDividendTypeEnum::REGULAR);
		$previousDividend3 = $this->createDividendMock(7, 1.0, StockAssetDividendTypeEnum::REGULAR);
		$previousDividend4 = $this->createDividendMock(10, 1.0, StockAssetDividendTypeEnum::REGULAR);

		$this->stockAssetDividendRepository
			->shouldReceive('findByStockAssetForYear')
			->with($stockAsset, 2023)
			->once()
			->andReturn([$previousDividend1, $previousDividend2, $previousDividend3, $previousDividend4]);

		$receivedDividend = $this->createDividendMock(1, 1.0, StockAssetDividendTypeEnum::REGULAR);
		$receivedSpecialDividend = $this->createDividendMock(2, 5.0, StockAssetDividendTypeEnum::SPECIAL);

		$this->stockAssetDividendRepository
			->shouldReceive('findByStockAssetForYear')
			->with($stockAsset, 2024)
			->once()
			->andReturn([$receivedDividend, $receivedSpecialDividend]);

		$this->entityManager->shouldReceive('flush')->twice();

		$this->facade->recalculate($this->forecastId);

		$this->assertTrue(true);
	}

	public function testRecalculateCreatesNewRecordWhenNotExists(): void
	{
		$stockAssetId = Uuid::uuid4();

		$forecast = Mockery::mock(StockAssetDividendForecast::class);
		$forecast->shouldReceive('getId')->andReturn($this->forecastId);
		$forecast->shouldReceive('getForYear')->andReturn(2024);
		$forecast->shouldReceive('getTrend')->andReturn(StockAssetDividendTrendEnum::NEUTRAL);
		$forecast->shouldReceive('recalculated')->once()->with($this->now);

		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('hasOpenPositions')->andReturn(true);
		$stockAsset->shouldReceive('doesPaysDividends')->andReturn(true);
		$stockAsset->shouldReceive('getId')->andReturn($stockAssetId);
		$stockAsset->shouldReceive('getCurrency')->andReturn(CurrencyEnum::USD);
		$stockAsset->shouldReceive('getTotalPiecesHeld')->andReturn(10);

		$this->stockAssetDividendForecastRepository
			->shouldReceive('getById')
			->with($this->forecastId)
			->once()
			->andReturn($forecast);

		// Žádný existující záznam
		$this->stockAssetDividendForecastRecordRepository
			->shouldReceive('findByStockAssetDividendForecast')
			->with($this->forecastId)
			->once()
			->andReturn([]);

		$this->stockAssetRepository
			->shouldReceive('findAll')
			->once()
			->andReturn([$stockAsset]);

		$previousDividend1 = $this->createDividendMock(1, 1.0, StockAssetDividendTypeEnum::REGULAR);
		$previousDividend2 = $this->createDividendMock(4, 1.0, StockAssetDividendTypeEnum::REGULAR);

		$this->stockAssetDividendRepository
			->shouldReceive('findByStockAssetForYear')
			->with($stockAsset, 2023)
			->once()
			->andReturn([$previousDividend1, $previousDividend2]);

		$receivedDividend = $this->createDividendMock(1, 1.0, StockAssetDividendTypeEnum::REGULAR);

		$this->stockAssetDividendRepository
			->shouldReceive('findByStockAssetForYear')
			->with($stockAsset, 2024)
			->once()
			->andReturn([$receivedDividend]);

		// Ověření, že se vytvoří nový záznam
		$this->entityManager
			->shouldReceive('persist')
			->once()
			->with(Mockery::type(StockAssetDividendForecastRecord::class));

		$this->entityManager->shouldReceive('flush')->twice();

		$this->facade->recalculate($this->forecastId);

		$this->assertTrue(true);
	}

	public function testRecalculateDeletesRecordWhenStockAssetStopsPaying(): void
	{
		$stockAssetId = Uuid::uuid4();

		$forecast = Mockery::mock(StockAssetDividendForecast::class);
		$forecast->shouldReceive('getId')->andReturn($this->forecastId);
		$forecast->shouldReceive('getForYear')->andReturn(2024);
		$forecast->shouldReceive('getTrend')->andReturn(StockAssetDividendTrendEnum::NEUTRAL);
		$forecast->shouldReceive('recalculated')->once()->with($this->now);

		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('hasOpenPositions')->andReturn(true);
		// Stock asset přestal platit dividendy
		$stockAsset->shouldReceive('doesPaysDividends')->andReturn(false);
		$stockAsset->shouldReceive('getId')->andReturn($stockAssetId);

		$existingRecord = Mockery::mock(StockAssetDividendForecastRecord::class);
		$existingRecord->shouldReceive('getStockAsset')->andReturn($stockAsset);

		$this->stockAssetDividendForecastRepository
			->shouldReceive('getById')
			->with($this->forecastId)
			->once()
			->andReturn($forecast);

		$this->stockAssetDividendForecastRecordRepository
			->shouldReceive('findByStockAssetDividendForecast')
			->with($this->forecastId)
			->once()
			->andReturn([$existingRecord]);

		$this->stockAssetRepository
			->shouldReceive('findAll')
			->once()
			->andReturn([$stockAsset]);

		$this->entityManager->shouldReceive('flush')->twice();
		// Ověření smazání záznamu
		$this->entityManager
			->shouldReceive('remove')
			->once()
			->with($existingRecord);

		$this->facade->recalculate($this->forecastId);

		$this->assertTrue(true);
	}

	public function testRecalculateWithOptimisticTrend(): void
	{
		$stockAssetId = Uuid::uuid4();

		$forecast = Mockery::mock(StockAssetDividendForecast::class);
		$forecast->shouldReceive('getId')->andReturn($this->forecastId);
		$forecast->shouldReceive('getForYear')->andReturn(2024);
		$forecast->shouldReceive('getTrend')->andReturn(StockAssetDividendTrendEnum::OPTIMISTIC_15);
		$forecast->shouldReceive('recalculated')->once()->with($this->now);

		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('hasOpenPositions')->andReturn(true);
		$stockAsset->shouldReceive('doesPaysDividends')->andReturn(true);
		$stockAsset->shouldReceive('getId')->andReturn($stockAssetId);
		$stockAsset->shouldReceive('getCurrency')->andReturn(CurrencyEnum::USD);
		$stockAsset->shouldReceive('getTotalPiecesHeld')->andReturn(10);

		$existingRecord = Mockery::mock(StockAssetDividendForecastRecord::class);
		$existingRecord->shouldReceive('getStockAsset')->andReturn($stockAsset);
		$existingRecord->shouldReceive('getCustomDividendUsedForCalculation')->andReturn(null);
		$existingRecord->shouldReceive('getExpectedSpecialDividendThisYearPerStock')->andReturn(null);

		// Použij Mockery::on() pro flexibilní validaci
		$existingRecord->shouldReceive('recalculate')->once()->with(
			Mockery::on(static fn ($val) => $val === [1]),
			Mockery::on(static fn ($val) => abs($val - 1.0) < 0.001),
			Mockery::on(static fn ($val) => $val === [1, 4, 7, 10]),
			10,
			Mockery::on(static fn ($val) => abs($val - 1.0) < 0.001),
			Mockery::on(static fn ($val) => abs($val - 1.15) < 0.001),
			Mockery::on(static fn ($val) => abs($val - 3.45) < 0.001),
			null,
			Mockery::on(static fn ($val) => abs($val - 0.0) < 0.001),
		);

		$this->stockAssetDividendForecastRepository
			->shouldReceive('getById')
			->with($this->forecastId)
			->once()
			->andReturn($forecast);

		$this->stockAssetDividendForecastRecordRepository
			->shouldReceive('findByStockAssetDividendForecast')
			->with($this->forecastId)
			->once()
			->andReturn([$existingRecord]);

		$this->stockAssetRepository
			->shouldReceive('findAll')
			->once()
			->andReturn([$stockAsset]);

		$previousDividend1 = $this->createDividendMock(1, 1.0, StockAssetDividendTypeEnum::REGULAR);
		$previousDividend2 = $this->createDividendMock(4, 1.0, StockAssetDividendTypeEnum::REGULAR);
		$previousDividend3 = $this->createDividendMock(7, 1.0, StockAssetDividendTypeEnum::REGULAR);
		$previousDividend4 = $this->createDividendMock(10, 1.0, StockAssetDividendTypeEnum::REGULAR);

		$this->stockAssetDividendRepository
			->shouldReceive('findByStockAssetForYear')
			->with($stockAsset, 2023)
			->once()
			->andReturn([$previousDividend1, $previousDividend2, $previousDividend3, $previousDividend4]);

		$receivedDividend = $this->createDividendMock(1, 1.0, StockAssetDividendTypeEnum::REGULAR);

		$this->stockAssetDividendRepository
			->shouldReceive('findByStockAssetForYear')
			->with($stockAsset, 2024)
			->once()
			->andReturn([$receivedDividend]);

		$this->entityManager->shouldReceive('flush')->twice();

		$this->facade->recalculate($this->forecastId);

		$this->assertTrue(true);
	}

	public function testRecalculateWithPessimisticTrend(): void
	{
		$stockAssetId = Uuid::uuid4();

		$forecast = Mockery::mock(StockAssetDividendForecast::class);
		$forecast->shouldReceive('getId')->andReturn($this->forecastId);
		$forecast->shouldReceive('getForYear')->andReturn(2024);
		// Trend -15%
		$forecast->shouldReceive('getTrend')->andReturn(StockAssetDividendTrendEnum::PESSIMISTIC_15);
		$forecast->shouldReceive('recalculated')->once()->with($this->now);

		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('hasOpenPositions')->andReturn(true);
		$stockAsset->shouldReceive('doesPaysDividends')->andReturn(true);
		$stockAsset->shouldReceive('getId')->andReturn($stockAssetId);
		$stockAsset->shouldReceive('getCurrency')->andReturn(CurrencyEnum::USD);
		$stockAsset->shouldReceive('getTotalPiecesHeld')->andReturn(10);

		$existingRecord = Mockery::mock(StockAssetDividendForecastRecord::class);
		$existingRecord->shouldReceive('getStockAsset')->andReturn($stockAsset);
		$existingRecord->shouldReceive('getCustomDividendUsedForCalculation')->andReturn(null);
		$existingRecord->shouldReceive('getExpectedSpecialDividendThisYearPerStock')->andReturn(null);

		// original = 1.0, adjusted = 1.0 * 0.85 = 0.85
		// expectedDividendPerStock = 3 * 0.85 = 2.55
		$existingRecord->shouldReceive('recalculate')->once()->with(
			[1],
			1.0,
			[1, 4, 7, 10],
			10,
			1.0,
			0.85, // adjusted price s -15% trendem
			2.55, // 3 zbývající * 0.85
			null,
			0.0,
		);

		$this->stockAssetDividendForecastRepository
			->shouldReceive('getById')
			->with($this->forecastId)
			->once()
			->andReturn($forecast);

		$this->stockAssetDividendForecastRecordRepository
			->shouldReceive('findByStockAssetDividendForecast')
			->with($this->forecastId)
			->once()
			->andReturn([$existingRecord]);

		$this->stockAssetRepository
			->shouldReceive('findAll')
			->once()
			->andReturn([$stockAsset]);

		$previousDividend1 = $this->createDividendMock(1, 1.0, StockAssetDividendTypeEnum::REGULAR);
		$previousDividend2 = $this->createDividendMock(4, 1.0, StockAssetDividendTypeEnum::REGULAR);
		$previousDividend3 = $this->createDividendMock(7, 1.0, StockAssetDividendTypeEnum::REGULAR);
		$previousDividend4 = $this->createDividendMock(10, 1.0, StockAssetDividendTypeEnum::REGULAR);

		$this->stockAssetDividendRepository
			->shouldReceive('findByStockAssetForYear')
			->with($stockAsset, 2023)
			->once()
			->andReturn([$previousDividend1, $previousDividend2, $previousDividend3, $previousDividend4]);

		$receivedDividend = $this->createDividendMock(1, 1.0, StockAssetDividendTypeEnum::REGULAR);

		$this->stockAssetDividendRepository
			->shouldReceive('findByStockAssetForYear')
			->with($stockAsset, 2024)
			->once()
			->andReturn([$receivedDividend]);

		$this->entityManager->shouldReceive('flush')->twice();

		$this->facade->recalculate($this->forecastId);

		$this->assertTrue(true);
	}

}
