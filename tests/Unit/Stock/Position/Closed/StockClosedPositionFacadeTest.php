<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Position\Closed;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Asset\Price\SummaryPriceService;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\JobRequest\JobRequestFacade;
use App\JobRequest\JobRequestTypeEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\Record\StockAssetDividendRecordFacade;
use App\Stock\Position\Closed\StockClosedPosition;
use App\Stock\Position\Closed\StockClosedPositionFacade;
use App\Stock\Position\Closed\StockClosedPositionRepository;
use App\Stock\Position\StockPosition;
use App\Stock\Position\StockPositionRepository;
use App\Test\UpdatedTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class StockClosedPositionFacadeTest extends TestCase
{

	private StockClosedPositionFacade $facade;

	private StockPositionRepository $stockPositionRepository;

	private StockClosedPositionRepository $stockClosedPositionRepository;

	private StockAssetRepository $stockAssetRepository;

	private EntityManagerInterface $entityManager;

	private DatetimeFactory $datetimeFactory;

	private CurrentAppAdminGetter $currentAppAdminGetter;

	private JobRequestFacade $jobRequestFacade;

	private StockAssetDividendRecordFacade $stockAssetDividendRecordFacade;

	private SummaryPriceService $summaryPriceService;

	public function setUp(): void
	{
		$this->stockPositionRepository = UpdatedTestCase::createMockWithIgnoreMethods(
			StockPositionRepository::class,
		);
		$this->stockClosedPositionRepository = UpdatedTestCase::createMockWithIgnoreMethods(
			StockClosedPositionRepository::class,
		);
		$this->stockAssetRepository = UpdatedTestCase::createMockWithIgnoreMethods(StockAssetRepository::class);
		$this->entityManager = UpdatedTestCase::createMockWithIgnoreMethods(EntityManagerInterface::class);
		$this->datetimeFactory = UpdatedTestCase::createMockWithIgnoreMethods(DatetimeFactory::class);
		$this->currentAppAdminGetter = UpdatedTestCase::createMockWithIgnoreMethods(CurrentAppAdminGetter::class);
		$this->jobRequestFacade = UpdatedTestCase::createMockWithIgnoreMethods(JobRequestFacade::class);
		$this->stockAssetDividendRecordFacade = UpdatedTestCase::createMockWithIgnoreMethods(
			StockAssetDividendRecordFacade::class,
		);
		$this->summaryPriceService = UpdatedTestCase::createMockWithIgnoreMethods(SummaryPriceService::class);

		$this->facade = new StockClosedPositionFacade(
			$this->stockPositionRepository,
			$this->stockClosedPositionRepository,
			$this->stockAssetRepository,
			$this->entityManager,
			$this->datetimeFactory,
			UpdatedTestCase::createMockWithIgnoreMethods(LoggerInterface::class),
			$this->currentAppAdminGetter,
			UpdatedTestCase::createMockWithIgnoreMethods(CurrencyConversionFacade::class),
			$this->summaryPriceService,
			$this->stockAssetDividendRecordFacade,
			$this->jobRequestFacade,
		);
	}

	public function testCreateClosedPosition(): void
	{
		$stockPositionId = Uuid::uuid4();
		$pricePerPiece = 150.0;
		$orderDate = new ImmutableDateTime();
		$totalInvestedAmount = new AssetPriceEmbeddable(1500.0, CurrencyEnum::USD);
		$differentBrokerAmount = false;
		$now = new ImmutableDateTime();

		$stockPosition = UpdatedTestCase::createMockWithIgnoreMethods(StockPosition::class);
		$appAdmin = UpdatedTestCase::createMockWithIgnoreMethods(AppAdmin::class);
		$adminUuid = Uuid::uuid4();

		$this->stockPositionRepository->shouldReceive('getById')
			->with($stockPositionId)
			->once()
			->andReturn($stockPosition);

		$this->datetimeFactory->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$appAdmin->shouldReceive('getId')
			->andReturn($adminUuid);

		$this->currentAppAdminGetter->shouldReceive('getAppAdmin')
			->andReturn($appAdmin);

		$this->entityManager->shouldReceive('persist')
			->once();

		$stockPosition->shouldReceive('closePosition')
			->once();

		$this->entityManager->shouldReceive('flush')
			->once();

		$this->entityManager->shouldReceive('refresh')
			->once();

		$this->jobRequestFacade->shouldReceive('addToQueue')
			->with(JobRequestTypeEnum::PORTFOLIO_GOAL_UPDATE)
			->once();

		$result = $this->facade->create(
			$stockPositionId,
			$pricePerPiece,
			$orderDate,
			$totalInvestedAmount,
			$differentBrokerAmount,
		);

		$this->assertInstanceOf(StockClosedPosition::class, $result);
	}

	public function testUpdateClosedPosition(): void
	{
		$stockClosedPositionId = Uuid::uuid4();
		$pricePerPiece = 160.0;
		$orderDate = new ImmutableDateTime();
		$totalInvestedAmount = new AssetPriceEmbeddable(1600.0, CurrencyEnum::USD);
		$differentBrokerAmount = true;
		$now = new ImmutableDateTime();

		$stockClosedPosition = UpdatedTestCase::createMockWithIgnoreMethods(StockClosedPosition::class);
		$appAdmin = UpdatedTestCase::createMockWithIgnoreMethods(AppAdmin::class);
		$adminUuid = Uuid::uuid4();
		$positionUuid = Uuid::uuid4();

		$this->stockClosedPositionRepository->shouldReceive('getById')
			->with($stockClosedPositionId)
			->once()
			->andReturn($stockClosedPosition);

		$this->datetimeFactory->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$appAdmin->shouldReceive('getId')
			->andReturn($adminUuid);

		$this->currentAppAdminGetter->shouldReceive('getAppAdmin')
			->andReturn($appAdmin);

		$stockClosedPosition->shouldReceive('update')
			->once();

		$stockClosedPosition->shouldReceive('getId')
			->andReturn($positionUuid);

		$this->entityManager->shouldReceive('flush')
			->once();

		$this->entityManager->shouldReceive('refresh')
			->once();

		$result = $this->facade->update(
			$stockClosedPositionId,
			$pricePerPiece,
			$orderDate,
			$totalInvestedAmount,
			$differentBrokerAmount,
		);

		$this->assertInstanceOf(StockClosedPosition::class, $result);
	}

	public function testGetAllStockClosedPositionsEmptyResult(): void
	{
		$this->stockAssetRepository->shouldReceive('findAll')
			->once()
			->andReturn([]);

		$result = $this->facade->getAllStockClosedPositions();

		$this->assertSame([], $result);
	}

	public function testGetAllStockClosedPositionsSkipsAssetsWithoutClosedPositions(): void
	{
		$stockAsset = UpdatedTestCase::createMockWithIgnoreMethods(StockAsset::class);
		$stockAsset->shouldReceive('hasClosedPositions')
			->once()
			->andReturn(false);

		$this->stockAssetRepository->shouldReceive('findAll')
			->once()
			->andReturn([$stockAsset]);

		$result = $this->facade->getAllStockClosedPositions();

		$this->assertSame([], $result);
	}

}
