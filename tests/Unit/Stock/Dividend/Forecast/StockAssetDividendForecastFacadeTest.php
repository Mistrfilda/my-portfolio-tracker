<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Dividend\Forecast;

use App\Stock\Dividend\Forecast\StockAssetDividendForecast;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastFacade;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRepository;
use App\Stock\Dividend\Forecast\StockAssetDividendTrendEnum;
use App\Test\UpdatedTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;
use Ramsey\Uuid\Uuid;

class StockAssetDividendForecastFacadeTest extends UpdatedTestCase
{

	private StockAssetDividendForecastFacade $facade;

	private StockAssetDividendForecastRepository $forecastRepository;

	private DatetimeFactory $datetimeFactory;

	private EntityManagerInterface $entityManager;

	protected function setUp(): void
	{
		parent::setUp();

		$this->forecastRepository = Mockery::mock(StockAssetDividendForecastRepository::class);
		$this->datetimeFactory = Mockery::mock(DatetimeFactory::class);
		$this->entityManager = Mockery::mock(EntityManagerInterface::class);

		$this->facade = new StockAssetDividendForecastFacade(
			$this->forecastRepository,
			$this->datetimeFactory,
			$this->entityManager,
		);
	}

	public function testCreate(): void
	{
		$now = new ImmutableDateTime();
		$this->datetimeFactory->shouldReceive('createNow')->once()->andReturn($now);

		$this->entityManager->shouldReceive('persist')
			->once()
			->with(Mockery::type(StockAssetDividendForecast::class));
		$this->entityManager->shouldReceive('flush')->once();

		$this->facade->create(2025, StockAssetDividendTrendEnum::NEUTRAL);

		$this->assertTrue(true);
	}

	public function testUpdate(): void
	{
		$id = Uuid::uuid4();
		$now = new ImmutableDateTime();

		$forecast = Mockery::mock(StockAssetDividendForecast::class);
		$forecast->shouldReceive('update')
			->once()
			->with(StockAssetDividendTrendEnum::NEUTRAL, $now);

		$this->forecastRepository->shouldReceive('getById')
			->once()
			->with($id)
			->andReturn($forecast);

		$this->datetimeFactory->shouldReceive('createNow')->once()->andReturn($now);
		$this->entityManager->shouldReceive('flush')->once();

		$this->facade->update($id, StockAssetDividendTrendEnum::NEUTRAL);

		$this->assertTrue(true);
	}

	public function testSetDefaultForYearWhenNoExistingDefault(): void
	{
		$id = Uuid::uuid4();

		$forecast = Mockery::mock(StockAssetDividendForecast::class);
		$forecast->shouldReceive('getId')->andReturn($id);
		$forecast->shouldReceive('getForYear')->andReturn(2025);
		$forecast->shouldReceive('defaultForYear')->once();

		$this->forecastRepository->shouldReceive('getById')
			->once()
			->with($id)
			->andReturn($forecast);

		$this->forecastRepository->shouldReceive('findByDefaultForYear')
			->once()
			->with(2025)
			->andReturn(null);

		$this->entityManager->shouldReceive('flush')->once();

		$this->facade->setDefaultForYear($id);

		$this->assertTrue(true);
	}

	public function testSetDefaultForYearWhenSameIsAlreadyDefault(): void
	{
		$id = Uuid::uuid4();

		$forecast = Mockery::mock(StockAssetDividendForecast::class);
		$forecast->shouldReceive('getId')->andReturn($id);
		$forecast->shouldReceive('getForYear')->andReturn(2025);

		$this->forecastRepository->shouldReceive('getById')
			->once()
			->with($id)
			->andReturn($forecast);

		$this->forecastRepository->shouldReceive('findByDefaultForYear')
			->once()
			->with(2025)
			->andReturn($forecast);

		$this->facade->setDefaultForYear($id);

		$this->assertTrue(true);
	}

	public function testSetDefaultForYearWhenDifferentIsDefault(): void
	{
		$id = Uuid::uuid4();
		$existingId = Uuid::uuid4();

		$existingForecast = Mockery::mock(StockAssetDividendForecast::class);
		$existingForecast->shouldReceive('getId')->andReturn($existingId);
		$existingForecast->shouldReceive('removeDefaultForYear')->once();

		$forecast = Mockery::mock(StockAssetDividendForecast::class);
		$forecast->shouldReceive('getId')->andReturn($id);
		$forecast->shouldReceive('getForYear')->andReturn(2025);
		$forecast->shouldReceive('defaultForYear')->once();

		$this->forecastRepository->shouldReceive('getById')
			->once()
			->with($id)
			->andReturn($forecast);

		$this->forecastRepository->shouldReceive('findByDefaultForYear')
			->once()
			->with(2025)
			->andReturn($existingForecast);

		$this->entityManager->shouldReceive('flush')->once();

		$this->facade->setDefaultForYear($id);

		$this->assertTrue(true);
	}

}
