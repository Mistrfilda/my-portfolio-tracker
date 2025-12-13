<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Dividend\Forecast;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\Forecast\StockAssetDividendForecast;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRecordFacade;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRecordRepository;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRepository;
use App\Stock\Dividend\Forecast\StockAssetDividendTrendEnum;
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

}
