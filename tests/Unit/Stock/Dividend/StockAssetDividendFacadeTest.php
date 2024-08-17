<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Dividend;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\StockAssetDividendFacade;
use App\Stock\Dividend\StockAssetDividendRepository;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\UuidInterface;

class StockAssetDividendFacadeTest extends TestCase
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

		$this->uuidInterface = Mockery::mock(UuidInterface::class);
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
		);

		$this->assertTrue(true);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
	}

}
