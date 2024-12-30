<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Dividend;

use App\Currency\CurrencyEnum;
use App\Notification\NotificationFacade;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\Downloader\Json\StockAssetJsonDividendDownloader;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Dividend\StockAssetDividendRepository;
use App\Stock\Price\Downloader\Json\JsonDataFolderService;
use App\Stock\Price\Downloader\Json\JsonDataSourceProviderFacade;
use App\System\SystemValueFacade;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use const DIRECTORY_SEPARATOR;

class StockAssetJsonDividendDownloaderTest extends TestCase
{

	private string $resultsFolder;

	private string $parsedResultsFolder;

	private JsonDataFolderService $jsonDataFolderService;

	private StockAssetRepository $stockAssetRepository;

	private StockAssetDividendRepository $stockAssetDividendRepository;

	private DatetimeFactory $datetimeFactory;

	private EntityManagerInterface $entityManager;

	private LoggerInterface $logger;

	private SystemValueFacade $systemValueFacade;

	private NotificationFacade $notificationFacade;

	private StockAssetJsonDividendDownloader $downloader;

	protected function setUp(): void
	{
		$this->resultsFolder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'results' . DIRECTORY_SEPARATOR;
		$this->parsedResultsFolder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'parsed-results' . DIRECTORY_SEPARATOR;

		FileSystem::createDir($this->resultsFolder);
		FileSystem::createDir($this->parsedResultsFolder);

		$this->jsonDataFolderService = $this->createMock(JsonDataFolderService::class);
		$this->jsonDataFolderService->method('getResultsFolder')->willReturn($this->resultsFolder);
		$this->jsonDataFolderService->method('getParsedResultsFolder')->willReturn($this->parsedResultsFolder);

		$this->stockAssetRepository = $this->createMock(StockAssetRepository::class);
		$this->stockAssetDividendRepository = $this->createMock(StockAssetDividendRepository::class);
		$this->datetimeFactory = $this->createMock(DatetimeFactory::class);
		$this->entityManager = $this->createMock(EntityManagerInterface::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->systemValueFacade = $this->createMock(SystemValueFacade::class);
		$this->notificationFacade = $this->createMock(NotificationFacade::class);

		$this->downloader = new StockAssetJsonDividendDownloader(
			$this->jsonDataFolderService,
			$this->stockAssetRepository,
			$this->stockAssetDividendRepository,
			$this->datetimeFactory,
			$this->entityManager,
			$this->logger,
			$this->systemValueFacade,
			$this->notificationFacade,
		);
	}

	public function testDownloadDividendRecordsNoFileExists(): void
	{
		$this->downloader->downloadDividendRecords();
		$this->assertTrue(true);
	}

	public function testDownloadDividendRecordsValidData(): void
	{
		$testFile = $this->resultsFolder . JsonDataSourceProviderFacade::STOCK_ASSET_DIVIDENDS_FILENAME;
		$sampleUuid = Uuid::uuid4()->toString();
		$sampleJson = [
			(object) [
				'id' => $sampleUuid,
				'currency' => 'USD',
				'textContent' => 'Dividend Jan 1, 2020 2.50',
				'html' => '<p>Some HTML content</p>',
			],
		];

		FileSystem::write($testFile, Json::encode($sampleJson));

		$mockStockAsset = $this->createMock(StockAsset::class);
		$mockStockAsset->method('getCurrency')->willReturn(CurrencyEnum::USD);
		$mockStockAsset->method('getName')->willReturn('Test Stock');

		$this->stockAssetRepository
			->method('getById')
			->with($this->equalTo(Uuid::fromString($sampleUuid)))
			->willReturn($mockStockAsset);

		$this->stockAssetDividendRepository
			->method('findOneByStockAssetExDate')
			->willReturn(null);

		$now = new ImmutableDateTime();
		$this->datetimeFactory->method('createNow')->willReturn($now);

		$this->entityManager
			->expects($this->once())
			->method('persist')
			->with($this->isInstanceOf(StockAssetDividend::class));

		$this->entityManager->expects($this->once())->method('flush');

		$this->notificationFacade
			->expects($this->once())
			->method('create');

		$this->downloader->downloadDividendRecords();

		$processedFile = $this->parsedResultsFolder . $now->getTimestamp() . '-' . JsonDataSourceProviderFacade::STOCK_ASSET_DIVIDENDS_FILENAME;

		$this->assertFileExists($processedFile);
	}

	public function testDownloadDividendRecordsFileIsProcessed(): void
	{
		$testFile = $this->resultsFolder . JsonDataSourceProviderFacade::STOCK_ASSET_DIVIDENDS_FILENAME;
		$sampleJson = [
			(object) [
				'id' => Uuid::uuid4()->toString(),
				'currency' => 'USD',
				'textContent' => '',
				'html' => '',
			],
		];

		FileSystem::write($testFile, Json::encode($sampleJson));

		$now = new ImmutableDateTime('now');
		$this->datetimeFactory->method('createNow')->willReturn($now);

		$this->downloader->downloadDividendRecords();

		$processedFile = $this->parsedResultsFolder . $now->getTimestamp() . '-' . JsonDataSourceProviderFacade::STOCK_ASSET_DIVIDENDS_FILENAME;

		$this->assertFileExists($processedFile);
		$this->assertFileDoesNotExist($testFile);
	}

}
