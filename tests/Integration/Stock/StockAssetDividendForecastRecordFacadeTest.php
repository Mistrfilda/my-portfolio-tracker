<?php

declare(strict_types = 1);

namespace App\Test\Integration\Stock;

use App\Admin\AppAdmin;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Dividend\Forecast\StockAssetDividendForecast;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRecordFacade;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRecordRepository;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRepository;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastStateEnum;
use App\Stock\Dividend\Forecast\StockAssetDividendTrendEnum;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Dividend\StockAssetDividendSourceEnum;
use App\Stock\Dividend\StockAssetDividendTypeEnum;
use App\Stock\Position\StockPosition;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Test\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class StockAssetDividendForecastRecordFacadeTest extends IntegrationTestCase
{

	private StockAssetDividendForecastRecordFacade $forecastRecordFacade;

	private StockAssetDividendForecastRecordRepository $forecastRecordRepository;

	private StockAssetDividendForecastRepository $forecastRepository;

	private EntityManagerInterface $entityManager;

	private StockAsset $stockAsset;

	private AppAdmin $appAdmin;

	protected function setUp(): void
	{
		parent::setUp();

		$this->forecastRecordFacade = $this->getService(StockAssetDividendForecastRecordFacade::class);
		$this->forecastRecordRepository = $this->getService(StockAssetDividendForecastRecordRepository::class);
		$this->forecastRepository = $this->getService(StockAssetDividendForecastRepository::class);
		$this->entityManager = $this->getService(EntityManagerInterface::class);

		$suffix = bin2hex(random_bytes(4));
		$this->appAdmin = new AppAdmin(
			'Test Admin Forecast',
			'test-admin-forecast-' . $suffix,
			'test-admin-forecast-' . $suffix . '@test.com',
			'password',
			new ImmutableDateTime(),
			false,
			false,
		);
		$this->entityManager->persist($this->appAdmin);

		$this->mockCurrentAppAdmin('Test Admin Forecast');

		$this->stockAsset = new StockAsset(
			'Forecast Test Company',
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			'FCST-' . $suffix,
			StockAssetExchange::NYSE,
			CurrencyEnum::USD,
			new ImmutableDateTime(),
			isin: null,
			stockAssetDividendSource: StockAssetDividendSourceEnum::WEB,
			dividendTax: 15.0,
			brokerDividendCurrency: null,
			shouldDownloadPrice: true,
			shouldDownloadValuation: false,
			watchlist: false,
			industry: null,
		);
		$this->entityManager->persist($this->stockAsset);
		$this->entityManager->flush();
	}

	public function testRecalculateCreatesRecordForAssetWithPositionsAndDividends(): void
	{
		$position = new StockPosition(
			$this->appAdmin,
			$this->stockAsset,
			10,
			150.0,
			new ImmutableDateTime('2024-01-01'),
			new AssetPriceEmbeddable(1500.0, CurrencyEnum::USD),
			false,
			new ImmutableDateTime(),
		);
		$this->entityManager->persist($position);

		$dividend = new StockAssetDividend(
			$this->stockAsset,
			new ImmutableDateTime('2025-03-15'),
			new ImmutableDateTime('2025-04-01'),
			null,
			CurrencyEnum::USD,
			0.50,
			new ImmutableDateTime(),
			StockAssetDividendTypeEnum::REGULAR,
		);
		$this->entityManager->persist($dividend);

		$dividend2 = new StockAssetDividend(
			$this->stockAsset,
			new ImmutableDateTime('2025-06-15'),
			new ImmutableDateTime('2025-07-01'),
			null,
			CurrencyEnum::USD,
			0.55,
			new ImmutableDateTime(),
			StockAssetDividendTypeEnum::REGULAR,
		);
		$this->entityManager->persist($dividend2);

		$forecast = new StockAssetDividendForecast(
			2026,
			StockAssetDividendTrendEnum::NEUTRAL,
			new ImmutableDateTime(),
		);
		$this->entityManager->persist($forecast);
		$this->entityManager->flush();
		$this->entityManager->refresh($this->stockAsset);

		$this->forecastRecordFacade->recalculate($forecast->getId());

		$records = $this->forecastRecordRepository->findByStockAssetDividendForecast($forecast->getId());

		$testAssetRecords = array_filter(
			$records,
			fn ($r) => $r->getStockAsset()->getId()->toString() === $this->stockAsset->getId()->toString(),
		);

		$this->assertCount(1, $testAssetRecords);
		$record = array_values($testAssetRecords)[0];
		$this->assertSame(CurrencyEnum::USD, $record->getCurrency());
		$this->assertSame(10, $record->getPiecesCurrentlyHeld());

		$updatedForecast = $this->forecastRepository->getById($forecast->getId());
		$this->assertSame(StockAssetDividendForecastStateEnum::FINISHED, $updatedForecast->getState());
		$this->assertNotNull($updatedForecast->getLastRecalculatedAt());
	}

	public function testUpdateCustomValuesForRecord(): void
	{
		$position = new StockPosition(
			$this->appAdmin,
			$this->stockAsset,
			10,
			150.0,
			new ImmutableDateTime('2024-01-01'),
			new AssetPriceEmbeddable(1500.0, CurrencyEnum::USD),
			false,
			new ImmutableDateTime(),
		);
		$this->entityManager->persist($position);

		$dividend = new StockAssetDividend(
			$this->stockAsset,
			new ImmutableDateTime('2025-03-15'),
			new ImmutableDateTime('2025-04-01'),
			null,
			CurrencyEnum::USD,
			0.50,
			new ImmutableDateTime(),
			StockAssetDividendTypeEnum::REGULAR,
		);
		$this->entityManager->persist($dividend);

		$forecast = new StockAssetDividendForecast(
			2026,
			StockAssetDividendTrendEnum::NEUTRAL,
			new ImmutableDateTime(),
		);
		$this->entityManager->persist($forecast);
		$this->entityManager->flush();
		$this->entityManager->refresh($this->stockAsset);

		$this->forecastRecordFacade->recalculate($forecast->getId());

		$records = $this->forecastRecordRepository->findByStockAssetDividendForecast($forecast->getId());
		$testAssetRecords = array_filter(
			$records,
			fn ($r) => $r->getStockAsset()->getId()->toString() === $this->stockAsset->getId()->toString(),
		);
		$this->assertCount(1, $testAssetRecords);
		$record = array_values($testAssetRecords)[0];

		$this->forecastRecordFacade->updateCustomValuesForRecord(
			$record->getId(),
			0.75,
			1.00,
		);

		$updatedRecord = $this->forecastRecordRepository->getById($record->getId());
		$this->assertSame(0.75, $updatedRecord->getCustomDividendUsedForCalculation());
		$this->assertSame(1.00, $updatedRecord->getExpectedSpecialDividendThisYearPerStock());

		$updatedForecast = $this->forecastRepository->getById($forecast->getId());
		$this->assertSame(StockAssetDividendForecastStateEnum::PENDING, $updatedForecast->getState());
	}

	public function testUpdateCustomValuesWithNulls(): void
	{
		$position = new StockPosition(
			$this->appAdmin,
			$this->stockAsset,
			5,
			200.0,
			new ImmutableDateTime('2024-01-01'),
			new AssetPriceEmbeddable(1000.0, CurrencyEnum::USD),
			false,
			new ImmutableDateTime(),
		);
		$this->entityManager->persist($position);

		$dividend = new StockAssetDividend(
			$this->stockAsset,
			new ImmutableDateTime('2025-06-15'),
			new ImmutableDateTime('2025-07-01'),
			null,
			CurrencyEnum::USD,
			0.30,
			new ImmutableDateTime(),
			StockAssetDividendTypeEnum::REGULAR,
		);
		$this->entityManager->persist($dividend);

		$forecast = new StockAssetDividendForecast(
			2026,
			StockAssetDividendTrendEnum::OPTIMISTIC_15,
			new ImmutableDateTime(),
		);
		$this->entityManager->persist($forecast);
		$this->entityManager->flush();
		$this->entityManager->refresh($this->stockAsset);

		$this->forecastRecordFacade->recalculate($forecast->getId());

		$records = $this->forecastRecordRepository->findByStockAssetDividendForecast($forecast->getId());
		$testAssetRecords = array_filter(
			$records,
			fn ($r) => $r->getStockAsset()->getId()->toString() === $this->stockAsset->getId()->toString(),
		);
		$this->assertCount(1, $testAssetRecords);
		$record = array_values($testAssetRecords)[0];

		$this->forecastRecordFacade->updateCustomValuesForRecord(
			$record->getId(),
			null,
			null,
		);

		$updatedRecord = $this->forecastRecordRepository->getById($record->getId());
		$this->assertNull($updatedRecord->getCustomDividendUsedForCalculation());
		$this->assertNull($updatedRecord->getExpectedSpecialDividendThisYearPerStock());
	}

}
