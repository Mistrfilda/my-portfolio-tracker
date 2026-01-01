<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Dividend;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Dividend\StockAssetDividendFacade;
use App\Stock\Dividend\StockAssetDividendRepository;
use App\Stock\Dividend\StockAssetDividendTypeEnum;
use App\Test\UpdatedTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class StockAssetDividendFacadeTest extends UpdatedTestCase
{

	private StockAssetDividendFacade $stockAssetDividendFacade;

	private EntityManagerInterface $entityManager;

	private StockAssetRepository $stockAssetRepository;

	private StockAssetDividendRepository $stockAssetDividendRepository;

	private DatetimeFactory $datetimeFactory;

	private UuidInterface $uuidInterface;

	protected function setUp(): void
	{
		parent::setUp();
		$this->uuidInterface = Uuid::uuid4();
		$this->entityManager = Mockery::mock(EntityManagerInterface::class);
		$this->stockAssetRepository = Mockery::mock(StockAssetRepository::class);
		$this->stockAssetDividendRepository = Mockery::mock(StockAssetDividendRepository::class);
		$this->datetimeFactory = Mockery::mock(DatetimeFactory::class);

		$this->stockAssetRepository->shouldReceive('getById')->andReturn(Mockery::mock(StockAsset::class));

		$this->stockAssetDividendFacade = new StockAssetDividendFacade(
			$this->stockAssetDividendRepository,
			$this->stockAssetRepository,
			$this->entityManager,
			$this->datetimeFactory,
		);
	}

	public function testCreate(): void
	{
		$this->entityManager->shouldReceive('persist')->once();
		$this->entityManager->shouldReceive('flush')->once();

		$this->datetimeFactory->shouldReceive('createNow')->once();

		$uuid = $this->uuidInterface;
		$exDate = new ImmutableDateTime('2022-12-01');
		$paymentDate = new ImmutableDateTime('2023-01-01');
		$declarationDate = new ImmutableDateTime('2022-11-01');
		$currency = CurrencyEnum::USD;
		$amount = 100.00;

		$this->stockAssetDividendFacade->create(
			$uuid,
			$exDate,
			$paymentDate,
			$declarationDate,
			$currency,
			$amount,
			StockAssetDividendTypeEnum::REGULAR,
		);

		$this->assertTrue(true);
	}

	public function testUpdate(): void
	{
		$dividendId = Uuid::uuid4();
		$exDate = new ImmutableDateTime('2023-01-15');
		$paymentDate = new ImmutableDateTime('2023-02-01');
		$declarationDate = new ImmutableDateTime('2023-01-01');
		$currency = CurrencyEnum::EUR;
		$amount = 150.00;
		$now = new ImmutableDateTime();

		$stockAssetDividend = Mockery::mock(StockAssetDividend::class);
		$stockAssetDividend->shouldReceive('update')
			->once()
			->with(
				$exDate,
				$paymentDate,
				$declarationDate,
				$currency,
				$amount,
				StockAssetDividendTypeEnum::SPECIAL,
				$now,
			);

		$this->stockAssetDividendRepository->shouldReceive('getById')
			->with($dividendId)
			->once()
			->andReturn($stockAssetDividend);

		$this->datetimeFactory->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$this->entityManager->shouldReceive('flush')->once();

		$this->stockAssetDividendFacade->update(
			$dividendId,
			$exDate,
			$paymentDate,
			$declarationDate,
			$currency,
			$amount,
			StockAssetDividendTypeEnum::SPECIAL,
		);

		$this->assertTrue(true);
	}

	public function testGetLastYearDividendRecordsForDashboard(): void
	{
		$now = new ImmutableDateTime('2024-06-15');
		$expectedDate = $now->deductDaysFromDatetime(380);

		$dividend1 = Mockery::mock(StockAssetDividend::class);
		$dividend2 = Mockery::mock(StockAssetDividend::class);
		$expectedDividends = [$dividend1, $dividend2];

		$this->datetimeFactory->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$this->stockAssetDividendRepository->shouldReceive('findGreaterThan')
			->once()
			->withArgs(
				static fn (ImmutableDateTime $date, int $limit): bool => $date->getTimestamp() === $expectedDate->getTimestamp() && $limit === 8,
			)
			->andReturn($expectedDividends);

		$result = $this->stockAssetDividendFacade->getLastYearDividendRecordsForDashboard();

		$this->assertCount(2, $result);
		$this->assertSame($expectedDividends, $result);
	}

	public function testGetLastDividends(): void
	{
		$limit = 5;

		$dividend1 = Mockery::mock(StockAssetDividend::class);
		$dividend2 = Mockery::mock(StockAssetDividend::class);
		$dividend3 = Mockery::mock(StockAssetDividend::class);
		$expectedDividends = [$dividend1, $dividend2, $dividend3];

		$this->stockAssetDividendRepository->shouldReceive('findLastDividends')
			->once()
			->with($limit)
			->andReturn($expectedDividends);

		$result = $this->stockAssetDividendFacade->getLastDividends($limit);

		$this->assertCount(3, $result);
		$this->assertSame($expectedDividends, $result);
	}

	public function testGetLastDividendsReturnsEmptyArray(): void
	{
		$limit = 10;

		$this->stockAssetDividendRepository->shouldReceive('findLastDividends')
			->once()
			->with($limit)
			->andReturn([]);

		$result = $this->stockAssetDividendFacade->getLastDividends($limit);

		$this->assertCount(0, $result);
		$this->assertIsArray($result);
	}

	public function testUpdateWithNullDeclarationDate(): void
	{
		$dividendId = Uuid::uuid4();
		$exDate = new ImmutableDateTime('2023-03-15');
		$paymentDate = new ImmutableDateTime('2023-04-01');
		$currency = CurrencyEnum::CZK;
		$amount = 25.50;
		$now = new ImmutableDateTime();

		$stockAssetDividend = Mockery::mock(StockAssetDividend::class);
		$stockAssetDividend->shouldReceive('update')
			->once()
			->with(
				$exDate,
				$paymentDate,
				null,
				$currency,
				$amount,
				StockAssetDividendTypeEnum::REGULAR,
				$now,
			);

		$this->stockAssetDividendRepository->shouldReceive('getById')
			->with($dividendId)
			->once()
			->andReturn($stockAssetDividend);

		$this->datetimeFactory->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$this->entityManager->shouldReceive('flush')->once();

		$this->stockAssetDividendFacade->update(
			$dividendId,
			$exDate,
			$paymentDate,
			null,
			$currency,
			$amount,
			StockAssetDividendTypeEnum::REGULAR,
		);

		$this->assertTrue(true);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
	}

}
