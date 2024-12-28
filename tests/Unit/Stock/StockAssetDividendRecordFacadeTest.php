<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock;

use App\Currency\CurrencyConversionFacade;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\Record\StockAssetDividendRecordFacade;
use App\Stock\Dividend\Record\StockAssetDividendRecordRepository;
use App\Stock\Dividend\Record\StockAssetDividendRecordService;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Dividend\StockAssetDividendRepository;
use App\Stock\Position\StockPosition;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mockery;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class StockAssetDividendRecordFacadeTest extends TestCase
{

	private StockAssetDividendRecordFacade|MockObject $stockAssetDividendRecordFacade;

	private StockAssetDividendRecordRepository|MockObject $stockAssetDividendRecordRepository;

	private StockAssetDividendRepository|MockObject $stockAssetDividendRepository;

	private StockAssetRepository|MockObject $stockAssetRepository;

	private StockAssetDividendRecordService|MockObject $stockAssetDividendRecordService;

	private EntityManagerInterface|MockObject $entityManager;

	private DatetimeFactory|MockObject $datetimeFactory;

	private LoggerInterface|MockObject $logger;

	protected function setUp(): void
	{
		$this->stockAssetDividendRecordRepository = Mockery::mock(StockAssetDividendRecordRepository::class);
		$this->stockAssetDividendRepository = Mockery::mock(StockAssetDividendRepository::class);
		$this->stockAssetRepository = Mockery::mock(StockAssetRepository::class);
		$this->stockAssetDividendRecordService = Mockery::mock(StockAssetDividendRecordService::class);
		$this->entityManager = Mockery::mock(EntityManagerInterface::class);
		$this->datetimeFactory = Mockery::mock(DatetimeFactory::class);
		$this->logger = Mockery::mock(LoggerInterface::class);

		$this->stockAssetDividendRecordFacade = new StockAssetDividendRecordFacade(
			$this->stockAssetDividendRecordRepository,
			$this->stockAssetDividendRepository,
			$this->stockAssetRepository,
			$this->stockAssetDividendRecordService,
			$this->entityManager,
			$this->datetimeFactory,
			Mockery::mock(CurrencyConversionFacade::class),
			$this->logger,
		);
	}

	public function testProcessAllDividends(): void
	{
		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('getName')->andReturn('test');
		$stockAsset->shouldReceive('getPositions')->andReturn([Mockery::mock(StockPosition::class)]);

		// You might need to adjust these return values to reflect your test scenario
		$this->stockAssetRepository->shouldReceive('findDividendPayers')->andReturn([$stockAsset]);
		$this->logger->shouldReceive('debug');

		$this->stockAssetDividendRepository->shouldReceive('findByStockAsset')->andReturn(
			[Mockery::mock(StockAssetDividend::class)],
		);

		$this->stockAssetDividendRecordService->shouldReceive('processDividendRecords')->andReturn(
			new ArrayCollection([]),
		);
		$this->stockAssetDividendRecordRepository->shouldReceive('findOneByStockDividend')->andReturn(null);
		$this->entityManager->shouldReceive('persist');
		$this->entityManager->shouldReceive('flush');

		$records = $this->stockAssetDividendRecordFacade->processAllDividends();
		$this->assertCount(0, $records);
	}

}
