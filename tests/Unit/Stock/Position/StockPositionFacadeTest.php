<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Position;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Asset\Price\AssetPriceService;
use App\Asset\Price\SummaryPrice;
use App\Asset\Price\SummaryPriceService;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\JobRequest\JobRequestFacade;
use App\JobRequest\JobRequestTypeEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetDetailDTO;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Position\StockPosition;
use App\Stock\Position\StockPositionFacade;
use App\Stock\Position\StockPositionRepository;
use App\Test\UpdatedTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class StockPositionFacadeTest extends TestCase
{

	private StockPositionFacade $facade;

	private StockPositionRepository $stockPositionRepository;

	private StockAssetRepository $stockAssetRepository;

	private SummaryPriceService $summaryPriceService;

	private CurrencyConversionFacade $currencyConversionFacade;

	private EntityManagerInterface $entityManager;

	private DatetimeFactory $datetimeFactory;

	private CurrentAppAdminGetter $currentAppAdminGetter;

	private JobRequestFacade $jobRequestFacade;

	public function setUp(): void
	{
		$this->stockPositionRepository = UpdatedTestCase::createMockWithIgnoreMethods(
			StockPositionRepository::class,
		);
		$this->stockAssetRepository = UpdatedTestCase::createMockWithIgnoreMethods(StockAssetRepository::class);
		$this->summaryPriceService = UpdatedTestCase::createMockWithIgnoreMethods(SummaryPriceService::class);
		$this->currencyConversionFacade = UpdatedTestCase::createMockWithIgnoreMethods(
			CurrencyConversionFacade::class,
		);
		$this->entityManager = UpdatedTestCase::createMockWithIgnoreMethods(EntityManagerInterface::class);
		$this->datetimeFactory = UpdatedTestCase::createMockWithIgnoreMethods(DatetimeFactory::class);
		$this->currentAppAdminGetter = UpdatedTestCase::createMockWithIgnoreMethods(CurrentAppAdminGetter::class);
		$this->jobRequestFacade = UpdatedTestCase::createMockWithIgnoreMethods(JobRequestFacade::class);

		$this->facade = new StockPositionFacade(
			$this->stockPositionRepository,
			$this->stockAssetRepository,
			UpdatedTestCase::createMockWithIgnoreMethods(AssetPriceService::class),
			$this->summaryPriceService,
			$this->currencyConversionFacade,
			$this->entityManager,
			$this->datetimeFactory,
			UpdatedTestCase::createMockWithIgnoreMethods(LoggerInterface::class),
			$this->currentAppAdminGetter,
			$this->jobRequestFacade,
		);
	}

	public function testCreatePosition(): void
	{
		$stockAssetId = Uuid::uuid4();
		$orderPiecesCount = 10;
		$pricePerPiece = 150.0;
		$orderDate = new ImmutableDateTime();
		$totalInvestedAmount = new AssetPriceEmbeddable(1500.0, CurrencyEnum::USD);
		$differentBrokerAmount = false;
		$now = new ImmutableDateTime();

		$stockAsset = UpdatedTestCase::createMockWithIgnoreMethods(StockAsset::class);
		$appAdmin = UpdatedTestCase::createMockWithIgnoreMethods(AppAdmin::class);
		$adminUuid = Uuid::uuid4();

		$this->stockAssetRepository->shouldReceive('getById')
			->with($stockAssetId)
			->once()
			->andReturn($stockAsset);

		$appAdmin->shouldReceive('getId')
			->andReturn($adminUuid);

		$this->currentAppAdminGetter->shouldReceive('getAppAdmin')
			->andReturn($appAdmin);

		$this->datetimeFactory->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$this->entityManager->shouldReceive('persist')
			->once();

		$this->entityManager->shouldReceive('flush')
			->once();

		$this->entityManager->shouldReceive('refresh')
			->once();

		$this->jobRequestFacade->shouldReceive('addToQueue')
			->with(JobRequestTypeEnum::PORTFOLIO_GOAL_UPDATE)
			->once();

		$result = $this->facade->create(
			$stockAssetId,
			$orderPiecesCount,
			$pricePerPiece,
			$orderDate,
			$totalInvestedAmount,
			$differentBrokerAmount,
		);

		$this->assertInstanceOf(StockPosition::class, $result);
	}

	public function testUpdatePosition(): void
	{
		$stockPositionId = Uuid::uuid4();
		$stockAssetId = Uuid::uuid4();
		$orderPiecesCount = 20;
		$pricePerPiece = 160.0;
		$orderDate = new ImmutableDateTime();
		$totalInvestedAmount = new AssetPriceEmbeddable(3200.0, CurrencyEnum::USD);
		$differentBrokerAmount = true;
		$now = new ImmutableDateTime();

		$stockAsset = UpdatedTestCase::createMockWithIgnoreMethods(StockAsset::class);
		$stockPosition = UpdatedTestCase::createMockWithIgnoreMethods(StockPosition::class);
		$appAdmin = UpdatedTestCase::createMockWithIgnoreMethods(AppAdmin::class);
		$adminUuid = Uuid::uuid4();
		$positionUuid = Uuid::uuid4();

		$this->stockAssetRepository->shouldReceive('getById')
			->with($stockAssetId)
			->once()
			->andReturn($stockAsset);

		$this->stockPositionRepository->shouldReceive('getById')
			->with($stockPositionId)
			->once()
			->andReturn($stockPosition);

		$appAdmin->shouldReceive('getId')
			->andReturn($adminUuid);

		$this->currentAppAdminGetter->shouldReceive('getAppAdmin')
			->andReturn($appAdmin);

		$this->datetimeFactory->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$stockPosition->shouldReceive('update')
			->once();

		$stockPosition->shouldReceive('getId')
			->andReturn($positionUuid);

		$this->entityManager->shouldReceive('flush')
			->once();

		$this->entityManager->shouldReceive('refresh')
			->once();

		$result = $this->facade->update(
			$stockPositionId,
			$stockAssetId,
			$orderPiecesCount,
			$pricePerPiece,
			$orderDate,
			$totalInvestedAmount,
			$differentBrokerAmount,
		);

		$this->assertInstanceOf(StockPosition::class, $result);
	}

	public function testGetCurrentPortfolioValueSummaryPrice(): void
	{
		$positions = [];
		$summaryPrice = new SummaryPrice(CurrencyEnum::CZK);

		$this->stockPositionRepository->shouldReceive('findAllOpened')
			->once()
			->andReturn($positions);

		$this->summaryPriceService->shouldReceive('getSummaryPriceForPositions')
			->with(CurrencyEnum::CZK, $positions)
			->once()
			->andReturn($summaryPrice);

		$result = $this->facade->getCurrentPortfolioValueSummaryPrice(CurrencyEnum::CZK);

		$this->assertSame($summaryPrice, $result);
	}

	public function testGetCurrentPortfolioValueInCzechStocks(): void
	{
		$positions = [];
		$summaryPrice = new SummaryPrice(CurrencyEnum::CZK);

		$this->stockPositionRepository->shouldReceive('findAllOpenedInCurrency')
			->with(CurrencyEnum::CZK)
			->once()
			->andReturn($positions);

		$this->summaryPriceService->shouldReceive('getSummaryPriceForPositions')
			->with(CurrencyEnum::CZK, $positions)
			->once()
			->andReturn($summaryPrice);

		$result = $this->facade->getCurrentPortfolioValueInCzechStocks(CurrencyEnum::CZK);

		$this->assertSame($summaryPrice, $result);
	}

	public function testGetCurrentPortfolioValueInUsdStocks(): void
	{
		$positions = [];
		$summaryPrice = new SummaryPrice(CurrencyEnum::USD);

		$this->stockPositionRepository->shouldReceive('findAllOpenedInCurrency')
			->with(CurrencyEnum::USD)
			->once()
			->andReturn($positions);

		$this->summaryPriceService->shouldReceive('getSummaryPriceForPositions')
			->with(CurrencyEnum::CZK, $positions)
			->once()
			->andReturn($summaryPrice);

		$result = $this->facade->getCurrentPortfolioValueInUsdStocks(CurrencyEnum::CZK);

		$this->assertSame($summaryPrice, $result);
	}

	public function testGetCurrentPortfolioValueInGbpStocks(): void
	{
		$positions = [];
		$summaryPrice = new SummaryPrice(CurrencyEnum::GBP);

		$this->stockPositionRepository->shouldReceive('findAllOpenedInCurrency')
			->with(CurrencyEnum::GBP)
			->once()
			->andReturn($positions);

		$this->summaryPriceService->shouldReceive('getSummaryPriceForPositions')
			->with(CurrencyEnum::CZK, $positions)
			->once()
			->andReturn($summaryPrice);

		$result = $this->facade->getCurrentPortfolioValueInGbpStocks(CurrencyEnum::CZK);

		$this->assertSame($summaryPrice, $result);
	}

	public function testGetCurrentPortfolioValueInEurStocks(): void
	{
		$positions = [];
		$summaryPrice = new SummaryPrice(CurrencyEnum::EUR);

		$this->stockPositionRepository->shouldReceive('findAllOpenedInCurrency')
			->with(CurrencyEnum::EUR)
			->once()
			->andReturn($positions);

		$this->summaryPriceService->shouldReceive('getSummaryPriceForPositions')
			->with(CurrencyEnum::CZK, $positions)
			->once()
			->andReturn($summaryPrice);

		$result = $this->facade->getCurrentPortfolioValueInEurStocks(CurrencyEnum::CZK);

		$this->assertSame($summaryPrice, $result);
	}

	public function testGetTotalInvestedAmountSummaryPrice(): void
	{
		$positions = [];
		$summaryPrice = new SummaryPrice(CurrencyEnum::CZK);

		$this->stockPositionRepository->shouldReceive('findAllOpened')
			->once()
			->andReturn($positions);

		$this->summaryPriceService->shouldReceive('getSummaryPriceForTotalInvestedAmountInBrokerCurrency')
			->with(CurrencyEnum::CZK, $positions)
			->once()
			->andReturn($summaryPrice);

		$result = $this->facade->getTotalInvestedAmountSummaryPrice(CurrencyEnum::CZK);

		$this->assertSame($summaryPrice, $result);
	}

	public function testGetStockAssetDetailDTONoPositions(): void
	{
		$stockAssetId = Uuid::uuid4();
		$stockAsset = UpdatedTestCase::createMockWithIgnoreMethods(StockAsset::class);

		$this->stockAssetRepository->shouldReceive('getById')
			->with($stockAssetId)
			->once()
			->andReturn($stockAsset);

		$stockAsset->shouldReceive('hasPositions')
			->once()
			->andReturn(false);

		$stockAsset->shouldReceive('getCurrency')
			->andReturn(CurrencyEnum::USD);

		$result = $this->facade->getStockAssetDetailDTO($stockAssetId);

		$this->assertInstanceOf(StockAssetDetailDTO::class, $result);
		$this->assertSame($stockAsset, $result->getStockAsset());
		$this->assertSame([], $result->getPositions());
		$this->assertSame(0, $result->getPiecesCount());
	}

	public function testGetStockAssetDetailDTOHasPositionsButNoOpenPositions(): void
	{
		$stockAssetId = Uuid::uuid4();
		$stockAsset = UpdatedTestCase::createMockWithIgnoreMethods(StockAsset::class);

		$this->stockAssetRepository->shouldReceive('getById')
			->with($stockAssetId)
			->once()
			->andReturn($stockAsset);

		$stockAsset->shouldReceive('hasPositions')
			->once()
			->andReturn(true);

		$stockAsset->shouldReceive('hasOpenPositions')
			->once()
			->andReturn(false);

		$stockAsset->shouldReceive('getCurrency')
			->andReturn(CurrencyEnum::USD);

		$result = $this->facade->getStockAssetDetailDTO($stockAssetId);

		$this->assertInstanceOf(StockAssetDetailDTO::class, $result);
		$this->assertSame([], $result->getPositions());
		$this->assertSame(0, $result->getPiecesCount());
	}

	public function testIncludeToTotalValues(): void
	{
		$result = $this->facade->includeToTotalValues();

		$this->assertTrue($result);
	}

}
