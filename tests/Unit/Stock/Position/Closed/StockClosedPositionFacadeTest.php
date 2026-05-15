<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Position\Closed;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Asset\Price\AssetPrice;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Asset\Price\PriceDiff;
use App\Asset\Price\SummaryPrice;
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

	private CurrencyConversionFacade $currencyConversionFacade;

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
		$this->currencyConversionFacade = UpdatedTestCase::createMockWithIgnoreMethods(CurrencyConversionFacade::class);
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
			$this->currencyConversionFacade,
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

	public function testGetAllStockClosedPositionsAggregatesClosedPositionsWithDividendsAndCzkConversion(): void
	{
		$firstCloseDate = new ImmutableDateTime('2026-04-01');
		$secondCloseDate = new ImmutableDateTime('2026-04-10');
		$stockAsset = UpdatedTestCase::createMockWithIgnoreMethods(StockAsset::class);
		$firstPosition = UpdatedTestCase::createMockWithIgnoreMethods(StockPosition::class);
		$secondPosition = UpdatedTestCase::createMockWithIgnoreMethods(StockPosition::class);
		$firstClosedPosition = UpdatedTestCase::createMockWithIgnoreMethods(StockClosedPosition::class);
		$secondClosedPosition = UpdatedTestCase::createMockWithIgnoreMethods(StockClosedPosition::class);

		$this->stockAssetRepository->shouldReceive('findAll')
			->once()
			->andReturn([$stockAsset]);
		$stockAsset->shouldReceive('hasClosedPositions')
			->once()
			->andReturn(true);
		$stockAsset->shouldReceive('getClosedPositions')
			->once()
			->andReturn([$firstPosition, $secondPosition]);

		$this->prepareClosedPosition(
			$stockAsset,
			$firstPosition,
			$firstClosedPosition,
			1000.0,
			1200.0,
			$firstCloseDate,
		);
		$this->prepareClosedPosition(
			$stockAsset,
			$secondPosition,
			$secondClosedPosition,
			500.0,
			600.0,
			$secondCloseDate,
		);

		$this->currencyConversionFacade->shouldReceive('getConvertedAssetPrice')
			->times(4)
			->withAnyArgs()
			->andReturnUsing(static function (AssetPrice $assetPrice, CurrencyEnum $currency): AssetPrice {
				self::assertSame(CurrencyEnum::CZK, $currency);

				return new AssetPrice(
					$assetPrice->getAsset(),
					$assetPrice->getPrice() * 22,
					CurrencyEnum::CZK,
				);
			});

		$dividendsSummary = new SummaryPrice(CurrencyEnum::USD, 90.0, 1);
		$this->stockAssetDividendRecordFacade->shouldReceive('getTotalSummaryPriceForStockAsset')
			->once()
			->with($stockAsset)
			->andReturn($dividendsSummary);
		$this->currencyConversionFacade->shouldReceive('getConvertedSummaryPrice')
			->times(3)
			->withAnyArgs()
			->andReturnUsing(static function (SummaryPrice $summaryPrice, CurrencyEnum $currency): SummaryPrice {
				if ($currency === CurrencyEnum::CZK) {
					return new SummaryPrice(
						CurrencyEnum::CZK,
						$summaryPrice->getPrice() * 22,
						$summaryPrice->getCounter(),
					);
				}

				return new SummaryPrice($currency, $summaryPrice->getPrice(), $summaryPrice->getCounter());
			});
		$this->summaryPriceService->shouldReceive('getSummaryPriceDiff')
			->times(6)
			->withAnyArgs()
			->andReturnUsing(
				static fn (SummaryPrice $summaryPrice, SummaryPrice $investedAmount): PriceDiff => new PriceDiff(
					$summaryPrice->getPrice() - $investedAmount->getPrice(),
					0.0,
					$summaryPrice->getCurrency(),
				),
			);

		$result = $this->facade->getAllStockClosedPositions();

		$this->assertCount(1, $result);
		$dto = $result[0];
		$this->assertSame($stockAsset, $dto->getStockAsset());
		$this->assertSame([$firstPosition, $secondPosition], $dto->getPositions());
		$this->assertSame(1500.0, $dto->getTotalInvestedAmount()->getPrice());
		$this->assertSame(1800.0, $dto->getTotalAmount()->getPrice());
		$this->assertSame(90.0, $dto->getDividendsSummary()?->getPrice());
		$this->assertSame(1890.0, $dto->getTotalAmountWithDividends()?->getPrice());
		$this->assertSame(1500.0, $dto->getTotalInvestedAmountInBrokerCurrency()->getPrice());
		$this->assertSame(1800.0, $dto->getTotalAmountInBrokerCurrency()->getPrice());
		$this->assertSame(1890.0, $dto->getTotalAmountInBrokerCurrencyWithDividends()?->getPrice());
		$this->assertSame(33000.0, $dto->getTotalInvestedAmountInBrokerCurrencyInCzk()->getPrice());
		$this->assertSame(39600.0, $dto->getTotalAmountInBrokerCurrencyInCzk()->getPrice());
		$this->assertSame(41580.0, $dto->getTotalAmountInBrokerCurrencyWithDividendsInCzk()?->getPrice());
		$this->assertSame(300.0, $dto->getTotalAmountPriceDiff()->getPriceDifference());
		$this->assertSame(390.0, $dto->getTotalAmountPriceDiffWithDividends()?->getPriceDifference());
		$this->assertSame(6600.0, $dto->getTotalAmountPriceDiffInCzk()->getPriceDifference());
		$this->assertSame(8580.0, $dto->getTotalAmountPriceDiffInCzkWithDividends()?->getPriceDifference());
	}

	private function prepareClosedPosition(
		StockAsset $stockAsset,
		StockPosition $position,
		StockClosedPosition $closedPosition,
		float $investedAmount,
		float $closeAmount,
		ImmutableDateTime $closeDate,
	): void
	{
		$position->shouldReceive('getStockClosedPosition')->andReturn($closedPosition);
		$position->shouldReceive('getTotalInvestedAmount')
			->andReturn(new AssetPrice($stockAsset, $investedAmount, CurrencyEnum::USD));
		$position->shouldReceive('getCurrentTotalAmount')
			->andReturn(new AssetPrice($stockAsset, $closeAmount, CurrencyEnum::USD));
		$position->shouldReceive('getTotalInvestedAmountInBrokerCurrency')
			->andReturn(new AssetPrice($stockAsset, $investedAmount, CurrencyEnum::USD));

		$closedPosition->shouldReceive('getTotalCloseAmountInBrokerCurrency')
			->andReturn(new AssetPrice($stockAsset, $closeAmount, CurrencyEnum::USD));
		$closedPosition->shouldReceive('getDate')->andReturn($closeDate);
	}

}
