<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Price\Downloader;

use App\Asset\Price\Downloader\JsonDataFolderService;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\StockAssetDividendSourceEnum;
use App\Stock\Price\Downloader\Json\JsonDataSourceProviderFacade;
use App\Stock\Price\Downloader\Json\JsonWebDataService;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;

class JsonDataSourceProviderFacadeTest extends TestCase
{

	private string $tempDir;

	private StockAssetRepository $stockAssetRepository;

	private DatetimeFactory $datetimeFactory;

	private JsonWebDataService $jsonWebDataService;

	private JsonDataSourceProviderFacade $facade;

	protected function setUp(): void
	{
		$this->tempDir = sys_get_temp_dir() . '/json_source_provider_test_' . uniqid() . '/';
		FileSystem::createDir($this->tempDir . JsonDataFolderService::REQUESTS_FOLDER);

		$this->stockAssetRepository = $this->createStub(StockAssetRepository::class);
		$this->datetimeFactory = $this->createStub(DatetimeFactory::class);
		$this->jsonWebDataService = $this->createStub(JsonWebDataService::class);
		$this->facade = new JsonDataSourceProviderFacade(
			24,
			$this->stockAssetRepository,
			$this->datetimeFactory,
			$this->jsonWebDataService,
		);
	}

	protected function tearDown(): void
	{
		FileSystem::delete($this->tempDir);
	}

	public function testGeneratePriceSourcesJsonFile(): void
	{
		$asset = $this->createStockAsset('Apple Inc.', 'AAPL', CurrencyEnum::USD);
		$this->stockAssetRepository = $this->createMock(StockAssetRepository::class);
		$this->datetimeFactory = $this->createMock(DatetimeFactory::class);
		$this->jsonWebDataService = $this->createMock(JsonWebDataService::class);
		$this->recreateFacade();

		$this->datetimeFactory->expects($this->once())
			->method('createNow')
			->willReturn(new ImmutableDateTime('2026-05-15 12:00:00'));
		$this->stockAssetRepository->expects($this->once())
			->method('findAllByAssetPriceDownloader')
			->with(
				StockAssetPriceDownloaderEnum::WEB_SCRAP,
				null,
				$this->callback(
					static fn (ImmutableDateTime $date): bool => $date->format('Y-m-d H:i:s') === '2026-05-14 12:00:00',
				),
			)
			->willReturn([$asset]);
		$this->jsonWebDataService->expects($this->once())
			->method('getStockAssetPriceUrl')
			->with($asset)
			->willReturn('https://example.test/price/AAPL');

		$this->facade->generatePriceSourcesJsonFile($this->tempDir);

		self::assertSame(
			[
				[
					'id' => $asset->getId()->toString(),
					'name' => 'Apple Inc.',
					'currency' => 'USD',
					'url' => 'https://example.test/price/AAPL',
				],
			],
			$this->readRequestFile(JsonDataSourceProviderFacade::STOCK_ASSET_PRICE_FILENAME),
		);
	}

	public function testGenerateDividendsJsonFile(): void
	{
		$asset = $this->createStockAsset('Microsoft', 'MSFT', CurrencyEnum::USD);
		$this->stockAssetRepository = $this->createMock(StockAssetRepository::class);
		$this->jsonWebDataService = $this->createMock(JsonWebDataService::class);
		$this->recreateFacade();

		$this->stockAssetRepository->expects($this->once())
			->method('findByStockAssetDividendSource')
			->with(StockAssetDividendSourceEnum::WEB)
			->willReturn([$asset]);
		$this->jsonWebDataService->expects($this->once())
			->method('getStockAssetDividendsUrl')
			->with($asset)
			->willReturn('https://example.test/dividends/MSFT');

		$this->facade->generateDividendsJsonFile($this->tempDir);

		self::assertSame(
			[
				[
					'id' => $asset->getId()->toString(),
					'name' => 'Microsoft',
					'currency' => 'USD',
					'url' => 'https://example.test/dividends/MSFT',
				],
			],
			$this->readRequestFile(JsonDataSourceProviderFacade::STOCK_ASSET_DIVIDENDS_FILENAME),
		);
	}

	public function testGenerateStockValuationJsonFileForRepositoryAssets(): void
	{
		$asset = $this->createStockAsset('Apple Inc.', 'AAPL', CurrencyEnum::USD);
		$this->stockAssetRepository = $this->createMock(StockAssetRepository::class);
		$this->jsonWebDataService = $this->createMock(JsonWebDataService::class);
		$this->recreateFacade();

		$this->stockAssetRepository->expects($this->once())
			->method('getAllActiveValuationAssets')
			->willReturn([$asset]);
		$this->jsonWebDataService->expects($this->once())
			->method('getKeyStatisticsDataUrl')
			->with($asset)
			->willReturn('https://example.test/key-statistics/AAPL');
		$this->jsonWebDataService->expects($this->once())
			->method('getFinancialsDataUrl')
			->with($asset)
			->willReturn('https://example.test/financials/AAPL');
		$this->jsonWebDataService->expects($this->once())
			->method('getAnalystInsightUrl')
			->with($asset)
			->willReturn('https://example.test/analyst-insights/AAPL');
		$this->jsonWebDataService->expects($this->once())
			->method('getStockAssetIndustryUrl')
			->willReturn('https://example.test/industry');

		$this->facade->generateStockValuationJsonFile($this->tempDir);

		self::assertSame(
			[
				[
					'id' => $asset->getId()->toString(),
					'name' => 'Apple Inc.',
					'currency' => 'USD',
					'url' => 'https://example.test/key-statistics/AAPL',
				],
			],
			$this->readRequestFile(JsonDataSourceProviderFacade::STOCK_ASSET_KEY_STATISTICS_FILENAME),
		);
		self::assertSame(
			[
				[
					'id' => $asset->getId()->toString(),
					'name' => 'Apple Inc.',
					'currency' => 'USD',
					'url' => 'https://example.test/financials/AAPL',
				],
			],
			$this->readRequestFile(JsonDataSourceProviderFacade::STOCK_ASSET_FINANCIALS_FILENAME),
		);
		self::assertSame(
			[
				[
					'id' => $asset->getId()->toString(),
					'name' => 'Apple Inc.',
					'currency' => 'USD',
					'url' => 'https://example.test/analyst-insights/AAPL',
				],
			],
			$this->readRequestFile(JsonDataSourceProviderFacade::STOCK_ASSET_ANALYST_INSIGHT),
		);
		self::assertSame(
			[
				[
					'id' => 'stockAssetIndustry',
					'name' => 'stockAssetIndustry',
					'currency' => 'USD',
					'url' => 'https://example.test/industry',
				],
			],
			$this->readRequestFile(JsonDataSourceProviderFacade::STOCK_ASSET_INDUSTRY),
		);
	}

	public function testGenerateStockValuationJsonFileForSelectedAsset(): void
	{
		$asset = $this->createStockAsset('Erste Group', 'EBS.VI', CurrencyEnum::EUR);
		$this->stockAssetRepository = $this->createMock(StockAssetRepository::class);
		$this->jsonWebDataService = $this->createMock(JsonWebDataService::class);
		$this->recreateFacade();

		$this->stockAssetRepository->expects($this->never())->method('getAllActiveValuationAssets');
		$this->jsonWebDataService->expects($this->once())
			->method('getKeyStatisticsDataUrl')
			->with($asset)
			->willReturn('https://example.test/key-statistics/EBS.VI');
		$this->jsonWebDataService->expects($this->once())
			->method('getFinancialsDataUrl')
			->with($asset)
			->willReturn('https://example.test/financials/EBS.VI');
		$this->jsonWebDataService->expects($this->once())
			->method('getAnalystInsightUrl')
			->with($asset)
			->willReturn('https://example.test/analyst-insights/EBS.VI');
		$this->jsonWebDataService->expects($this->once())
			->method('getStockAssetIndustryUrl')
			->willReturn('https://example.test/industry');

		$this->facade->generateStockValuationJsonFile($this->tempDir, $asset);

		self::assertSame(
			[
				[
					'id' => $asset->getId()->toString(),
					'name' => 'Erste Group',
					'currency' => 'EUR',
					'url' => 'https://example.test/key-statistics/EBS.VI',
				],
			],
			$this->readRequestFile(JsonDataSourceProviderFacade::STOCK_ASSET_KEY_STATISTICS_FILENAME),
		);
	}

	/**
	 * @return array<mixed>
	 */
	private function readRequestFile(string $filename): array
	{
		return Json::decode(
			file_get_contents($this->tempDir . JsonDataFolderService::REQUESTS_FOLDER . $filename),
			forceArrays: true,
		);
	}

	private function createStockAsset(string $name, string $ticker, CurrencyEnum $currency): StockAsset
	{
		return new StockAsset(
			$name,
			StockAssetPriceDownloaderEnum::WEB_SCRAP,
			$ticker,
			StockAssetExchange::NYSE,
			$currency,
			new ImmutableDateTime('2026-05-15 12:00:00'),
			null,
			StockAssetDividendSourceEnum::WEB,
			null,
			null,
			true,
			true,
			false,
		);
	}

	private function recreateFacade(): void
	{
		$this->facade = new JsonDataSourceProviderFacade(
			24,
			$this->stockAssetRepository,
			$this->datetimeFactory,
			$this->jsonWebDataService,
		);
	}

}
