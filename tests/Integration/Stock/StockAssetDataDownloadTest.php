<?php

declare(strict_types = 1);

namespace App\Test\Integration\Stock;

use App\Asset\Price\Downloader\JsonDataFolderService;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\Download\StockAssetDataImportGuard;
use App\Stock\Asset\Download\StockAssetDataType;
use App\Stock\Asset\Download\StockAssetDownloadFiles;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\StockAssetDividendSourceEnum;
use App\Stock\Price\Downloader\Json\JsonDataDownloaderFacade;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Price\StockAssetPriceRecord;
use App\Stock\Price\StockAssetPriceRecordRepository;
use App\Stock\Valuation\Data\StockValuationData;
use App\Stock\Valuation\Data\StockValuationDataRepository;
use App\Stock\Valuation\StockValuationTypeEnum;
use App\System\SystemValueFacade;
use App\Test\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;

class StockAssetDataDownloadTest extends IntegrationTestCase
{

	private EntityManagerInterface $em;

	protected function setUp(): void
	{
		parent::setUp();
		$this->em = $this->getService(EntityManagerInterface::class);
		$this->em->getConnection()->beginTransaction();
	}

	protected function tearDown(): void
	{
		$this->em->getConnection()->rollBack();
		$this->em->clear();
		parent::tearDown();
	}

	public function testTwentyFreshStocksThenOneNewStockAndRepeatedChecks(): void
	{
		$at = new ImmutableDateTime('2099-09-24 12:30:00');
		$cutoff = $at->deductHoursFromDatetime(1);
		$repo = $this->getService(StockAssetRepository::class);
		$baseline = [];
		foreach (StockAssetDataType::cases() as $type) {
			$baseline[$type->value] = $repo->countForDataType($type);
		}

		for ($i = 0; $i < 20; $i++) {
			$this->markAllChecked($this->asset($at->deductDaysFromDatetime(1)), $at);
		}

		$this->em->flush();
		foreach (StockAssetDataType::cases() as $type) {
			$this->assertSame(20, $repo->countForDataType($type, $cutoff));
		}

		$new = $this->asset($at);
		$this->em->flush();
		$this->assertNull($new->getPriceDownloadedAt());
		$this->assertSame(0.0, $new->getTrend($at));
		foreach (StockAssetDataType::cases() as $type) {
			$this->assertSame($baseline[$type->value] + 21, $repo->countForDataType($type));
			$this->assertSame(20, $repo->countForDataType($type, $cutoff));
			$oldStale = $repo->countStaleForDataType($type, $cutoff, $at->modify('-15 minutes'));
			$this->assertSame($oldStale + 1, $repo->countStaleForDataType($type, $cutoff, $at));
		}

		$this->assertContains($new, $repo->findAllByAssetPriceDownloader(
			StockAssetPriceDownloaderEnum::WEB_SCRAP,
			priceDownloadedBefore: $cutoff,
		));

		$this->markAllChecked($new, $at);
		$this->markAllChecked($new, $at);
		$this->em->flush();
		foreach (StockAssetDataType::cases() as $type) {
			$this->assertSame(21, $repo->countForDataType($type, $cutoff));
		}

		$disabled = $this->asset($at, enabled: false);
		$this->markAllChecked($disabled, $at);
		$manualDividend = $this->asset($at, dividendSource: StockAssetDividendSourceEnum::MANUAL);
		$this->markAllChecked($manualDividend, $at);
		$this->em->flush();
		$this->assertSame(21, $repo->countForDataType(StockAssetDataType::DIVIDENDS, $cutoff));
		$this->assertSame(22, $repo->countForDataType(StockAssetDataType::PRICE, $cutoff));
	}

	public function testDelayedAndDuplicateImportsCannotOverwriteNewerPrice(): void
	{
		$now = new ImmutableDateTime('2026-09-24 16:00:00');
		$asset = $this->asset($now);
		$this->em->flush();
		$folder = sys_get_temp_dir() . '/stock-import-test-' . Uuid::uuid4();
		$folders = new JsonDataFolderService($folder);
		$files = new StockAssetDownloadFiles($folders, $asset->getId());
		$clock = $this->createStub(DatetimeFactory::class);
		$clock->method('createNow')->willReturn($now);
		$clock->method('createToday')->willReturn($now->setTime(0, 0));
		$globalStats = $this->createMock(SystemValueFacade::class);
		$globalStats->expects($this->never())->method('updateValue');
		$importer = new JsonDataDownloaderFacade(
			$folders,
			$this->getService(StockAssetRepository::class),
			$clock,
			$this->getService(StockAssetPriceRecordRepository::class),
			$this->em,
			new NullLogger(),
			$globalStats,
			$this->getService(StockAssetDataImportGuard::class),
		);
		try {
			foreach ([[200, 0], [100, 3600], [50, 0]] as [$price, $delay]) {
				FileSystem::write($folders->getResultsFolder() . 'prices.json', Json::encode([[
					'id' => $asset->getId()->toString(),
					'price' => (string) $price,
					'currency' => 'USD',
					'downloadedAt' => $now->getTimestamp() - $delay,
				]]));
				$importer->getPriceForAssets($files);
			}

			$this->em->refresh($asset);
			$this->assertSame(200.0, $asset->getAssetCurrentPrice()->getPrice());
			$this->assertEquals($now, $asset->getPriceDownloadedAt());
			$this->assertSame(
				1,
				$this->em->getRepository(StockAssetPriceRecord::class)->count(['stockAsset' => $asset]),
			);
		} finally {
			FileSystem::delete($folder);
		}
	}

	public function testValuationAndAnalystCleanupAreIndependent(): void
	{
		$now = new ImmutableDateTime('2026-09-24 16:00:00');
		$asset = $this->asset($now);
		foreach ([StockValuationTypeEnum::MARKET_CAP, StockValuationTypeEnum::ANALYST_PRICE_TARGET_AVERAGE] as $type) {
			$this->em->persist(new StockValuationData(
				$asset,
				$type,
				$type->getTypeGroup(),
				$type->getTypeValueType(),
				$now,
				'100',
				100,
				CurrencyEnum::USD,
				$now,
			));
		}

		$this->em->flush();
		$repo = $this->getService(StockValuationDataRepository::class);
		$repo->removeTodayData($asset, $now);
		$repo->updateLastActive($asset);
		$remaining = $this->em->getRepository(StockValuationData::class)->findBy(['stockAsset' => $asset]);
		$this->assertCount(1, $remaining);
		$this->em->refresh($remaining[0]);
		$this->assertSame(StockValuationTypeEnum::ANALYST_PRICE_TARGET_AVERAGE, $remaining[0]->getValuationType());
		$this->assertTrue($remaining[0]->isLastActive());
		$repo->removeAnalystData($asset, $now);
		$this->assertSame(0, $this->em->getRepository(StockValuationData::class)->count(['stockAsset' => $asset]));
	}

	private function asset(
		ImmutableDateTime $at,
		bool $enabled = true,
		StockAssetDividendSourceEnum $dividendSource = StockAssetDividendSourceEnum::WEB,
	): StockAsset
	{
		$asset = new StockAsset(
			'Download test',
			StockAssetPriceDownloaderEnum::WEB_SCRAP,
			Uuid::uuid4()->toString(),
			StockAssetExchange::NYSE,
			CurrencyEnum::USD,
			$at,
			null,
			$dividendSource,
			null,
			null,
			$enabled,
			$enabled,
			false,
		);
		$this->em->persist($asset);
		return $asset;
	}

	private function markAllChecked(StockAsset $asset, ImmutableDateTime $at): void
	{
		$price = new StockAssetPriceRecord(
			$at->setTime(0, 0),
			CurrencyEnum::USD,
			100,
			$asset,
			StockAssetPriceDownloaderEnum::WEB_SCRAP,
			$at,
		);
		$asset->setCurrentPrice($price, $at);
		$asset->markDividendsChecked($at);
		$asset->markValuationDownloaded($at);
		$asset->markAnalystInsightsDownloaded($at);
	}

}
