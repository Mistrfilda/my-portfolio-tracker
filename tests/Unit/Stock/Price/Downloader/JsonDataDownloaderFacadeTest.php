<?php

declare(strict_types = 1);


namespace App\Test\Unit\Stock\Price\Downloader;


use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Price\Downloader\Json\JsonDataDownloaderFacade;
use App\Stock\Price\Downloader\Json\JsonDataFolderService;
use App\Stock\Price\Downloader\Json\JsonDataSourceProviderFacade;
use App\Stock\Price\StockAssetPriceRecord;
use App\Stock\Price\StockAssetPriceRecordRepository;
use App\System\SystemValueEnum;
use App\System\SystemValueFacade;
use App\Test\UpdatedTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class JsonDataDownloaderFacadeTest extends UpdatedTestCase
{

	private JsonDataDownloaderFacade $jsonDataDownloaderFacade;

	private JsonDataFolderService $jsonDataFolderService;

	private StockAssetRepository $stockAssetRepository;

	private DatetimeFactory $datetimeFactory;

	private StockAssetPriceRecordRepository $stockAssetPriceRecordRepository;

	private EntityManagerInterface $entityManager;

	private LoggerInterface $logger;

	private SystemValueFacade $systemValueFacade;

	private string $tempDir;

	protected function setUp(): void
	{
		$this->tempDir = sys_get_temp_dir() . '/json_downloader_test_' . uniqid();
		mkdir($this->tempDir);
		mkdir($this->tempDir . JsonDataFolderService::RESULTS_FOLDER);
		mkdir($this->tempDir . JsonDataFolderService::PARSED_RESULTS_FOLDER);

		$this->jsonDataFolderService = new JsonDataFolderService($this->tempDir);
		$this->stockAssetRepository = Mockery::mock(StockAssetRepository::class);
		$this->datetimeFactory = Mockery::mock(DatetimeFactory::class);
		$this->stockAssetPriceRecordRepository = Mockery::mock(StockAssetPriceRecordRepository::class);
		$this->entityManager = Mockery::mock(EntityManagerInterface::class);
		$this->logger = Mockery::mock(LoggerInterface::class);
		$this->systemValueFacade = Mockery::mock(SystemValueFacade::class);

		$this->jsonDataDownloaderFacade = new JsonDataDownloaderFacade(
			$this->jsonDataFolderService,
			$this->stockAssetRepository,
			$this->datetimeFactory,
			$this->stockAssetPriceRecordRepository,
			$this->entityManager,
			$this->logger,
			$this->systemValueFacade,
		);
	}

	protected function tearDown(): void
	{
		// Clean up temp directory
		$this->deleteDirectory($this->tempDir);
	}

	public function testGetPriceForAssetsReturnsEmptyArrayWhenFileDoesNotExist(): void
	{
		$result = $this->jsonDataDownloaderFacade->getPriceForAssets();
		$this->assertIsArray($result);
		$this->assertCount(0, $result);
	}

	public function testGetPriceForAssetsCreatesNewPriceRecord(): void
	{
		$stockAssetId = Uuid::uuid4();
		$today = new ImmutableDateTime('2024-01-15');
		$now = new ImmutableDateTime('2024-01-15 10:30:00');

		// Prepare JSON file
		$jsonData = [
			[
				'id' => $stockAssetId->toString(),
				'currency' => 'USD',
				'price' => '$123.45',
			],
		];

		file_put_contents(
			$this->jsonDataFolderService->getResultsFolder() . JsonDataSourceProviderFacade::STOCK_ASSET_PRICE_FILENAME,
			json_encode($jsonData),
		);

		// Mock stock asset
		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('getName')->andReturn('Test Stock');
		$stockAsset->shouldReceive('getCurrency')->andReturn(CurrencyEnum::USD);
		$stockAsset->shouldReceive('setCurrentPrice')->once();

		$this->stockAssetRepository->shouldReceive('getById')
			->with(Mockery::on(fn($uuid) => $uuid->toString() === $stockAssetId->toString()))
			->once()
			->andReturn($stockAsset);

		$this->datetimeFactory->shouldReceive('createToday')->once()->andReturn($today);
		$this->datetimeFactory->shouldReceive('createNow')->times(2)->andReturn($now);

		$this->stockAssetPriceRecordRepository->shouldReceive('findByStockAssetAndDate')
			->with($stockAsset, $today)
			->once()
			->andReturn(null);

		$this->logger->shouldReceive('info')->once();

		$this->entityManager->shouldReceive('persist')->once();
		$this->entityManager->shouldReceive('flush')->once();

		$this->systemValueFacade->shouldReceive('updateValue')
			->with(SystemValueEnum::PUPPETER_UPDATED_AT, Mockery::any())
			->once();

		$result = $this->jsonDataDownloaderFacade->getPriceForAssets();

		$this->assertIsArray($result);
		$this->assertCount(1, $result);
		$this->assertInstanceOf(StockAssetPriceRecord::class, $result[0]);
	}

	public function testGetPriceForAssetsUpdatesExistingPriceRecord(): void
	{
		$stockAssetId = Uuid::uuid4();
		$today = new ImmutableDateTime('2024-01-15');
		$now = new ImmutableDateTime('2024-01-15 10:30:00');

		// Prepare JSON file
		$jsonData = [
			[
				'id' => $stockAssetId->toString(),
				'currency' => 'USD',
				'price' => '150.75',
			],
		];

		file_put_contents(
			$this->jsonDataFolderService->getResultsFolder() . JsonDataSourceProviderFacade::STOCK_ASSET_PRICE_FILENAME,
			json_encode($jsonData),
		);

		// Mock stock asset
		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('getName')->andReturn('Test Stock');
		$stockAsset->shouldReceive('getCurrency')->andReturn(CurrencyEnum::USD);
		$stockAsset->shouldReceive('setCurrentPrice')->once();

		// Mock existing price record
		$existingPriceRecord = Mockery::mock(StockAssetPriceRecord::class);
		$existingPriceRecord->shouldReceive('updatePrice')->with(150.75, $now)->once();

		$this->stockAssetRepository->shouldReceive('getById')
			->with(Mockery::on(fn($uuid) => $uuid->toString() === $stockAssetId->toString()))
			->once()
			->andReturn($stockAsset);

		$this->datetimeFactory->shouldReceive('createToday')->once()->andReturn($today);
		$this->datetimeFactory->shouldReceive('createNow')->times(2)->andReturn($now);

		$this->stockAssetPriceRecordRepository->shouldReceive('findByStockAssetAndDate')
			->with($stockAsset, $today)
			->once()
			->andReturn($existingPriceRecord);

		$this->logger->shouldReceive('info')->once();

		$this->entityManager->shouldReceive('flush')->once();

		$this->systemValueFacade->shouldReceive('updateValue')
			->with(SystemValueEnum::PUPPETER_UPDATED_AT, Mockery::any())
			->once();

		$result = $this->jsonDataDownloaderFacade->getPriceForAssets();

		$this->assertIsArray($result);
		$this->assertCount(1, $result);
		$this->assertSame($existingPriceRecord, $result[0]);
	}

	public function testGetPriceForAssetsProcessesMultipleAssets(): void
	{
		$stockAssetId1 = Uuid::uuid4();
		$stockAssetId2 = Uuid::uuid4();
		$today = new ImmutableDateTime('2024-01-15');
		$now = new ImmutableDateTime('2024-01-15 10:30:00');

		// Prepare JSON file with multiple assets
		$jsonData = [
			[
				'id' => $stockAssetId1->toString(),
				'currency' => 'USD',
				'price' => '100.00',
			],
			[
				'id' => $stockAssetId2->toString(),
				'currency' => 'EUR',
				'price' => '€200.50',
			],
		];

		file_put_contents(
			$this->jsonDataFolderService->getResultsFolder() . JsonDataSourceProviderFacade::STOCK_ASSET_PRICE_FILENAME,
			json_encode($jsonData),
		);

		// Mock first stock asset
		$stockAsset1 = Mockery::mock(StockAsset::class);
		$stockAsset1->shouldReceive('getName')->andReturn('Test Stock 1');
		$stockAsset1->shouldReceive('getCurrency')->andReturn(CurrencyEnum::USD);
		$stockAsset1->shouldReceive('setCurrentPrice')->once();

		// Mock second stock asset
		$stockAsset2 = Mockery::mock(StockAsset::class);
		$stockAsset2->shouldReceive('getName')->andReturn('Test Stock 2');
		$stockAsset2->shouldReceive('getCurrency')->andReturn(CurrencyEnum::EUR);
		$stockAsset2->shouldReceive('setCurrentPrice')->once();

		$this->stockAssetRepository->shouldReceive('getById')
			->with(Mockery::on(fn($uuid) => $uuid->toString() === $stockAssetId1->toString()))
			->once()
			->andReturn($stockAsset1);

		$this->stockAssetRepository->shouldReceive('getById')
			->with(Mockery::on(fn($uuid) => $uuid->toString() === $stockAssetId2->toString()))
			->once()
			->andReturn($stockAsset2);

		$this->datetimeFactory->shouldReceive('createToday')->once()->andReturn($today);
		$this->datetimeFactory->shouldReceive('createNow')->times(2)->andReturn($now);

		$this->stockAssetPriceRecordRepository->shouldReceive('findByStockAssetAndDate')
			->twice()
			->andReturn(null);

		$this->logger->shouldReceive('info')->twice();

		$this->entityManager->shouldReceive('persist')->twice();
		$this->entityManager->shouldReceive('flush')->once();

		$this->systemValueFacade->shouldReceive('updateValue')
			->with(SystemValueEnum::PUPPETER_UPDATED_AT, Mockery::any())
			->once();

		$result = $this->jsonDataDownloaderFacade->getPriceForAssets();

		$this->assertIsArray($result);
		$this->assertCount(2, $result);
	}

	public function testGetPriceForAssetsMovesFileToProcessedFolder(): void
	{
		$stockAssetId = Uuid::uuid4();
		$today = new ImmutableDateTime('2024-01-15');
		$now = new ImmutableDateTime('2024-01-15 10:30:00');

		$jsonData = [
			[
				'id' => $stockAssetId->toString(),
				'currency' => 'USD',
				'price' => '100.00',
			],
		];

		$sourceFile = $this->jsonDataFolderService->getResultsFolder() . JsonDataSourceProviderFacade::STOCK_ASSET_PRICE_FILENAME;
		file_put_contents($sourceFile, json_encode($jsonData));

		// Mock stock asset
		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('getName')->andReturn('Test Stock');
		$stockAsset->shouldReceive('getCurrency')->andReturn(CurrencyEnum::USD);
		$stockAsset->shouldReceive('setCurrentPrice')->once();

		$this->stockAssetRepository->shouldReceive('getById')->andReturn($stockAsset);
		$this->datetimeFactory->shouldReceive('createToday')->andReturn($today);
		$this->datetimeFactory->shouldReceive('createNow')->andReturn($now);
		$this->stockAssetPriceRecordRepository->shouldReceive('findByStockAssetAndDate')->andReturn(null);
		$this->logger->shouldReceive('info');
		$this->entityManager->shouldReceive('persist');
		$this->entityManager->shouldReceive('flush');
		$this->systemValueFacade->shouldReceive('updateValue');

		$this->jsonDataDownloaderFacade->getPriceForAssets();

		// Verify source file was deleted
		$this->assertFileDoesNotExist($sourceFile);

		// Verify file was copied to processed folder
		$processedFile = sprintf(
			'%s%s-%s',
			$this->jsonDataFolderService->getParsedResultsFolder(),
			$now->getTimestamp(),
			JsonDataSourceProviderFacade::STOCK_ASSET_PRICE_FILENAME,
		);
		$this->assertFileExists($processedFile);
	}

	private function deleteDirectory(string $dir): void
	{
		if (!file_exists($dir)) {
			return;
		}

		$files = array_diff(scandir($dir), ['.', '..']);
		foreach ($files as $file) {
			$path = $dir . '/' . $file;
			is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
		}
		rmdir($dir);
	}

}
