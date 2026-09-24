<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Price\Downloader;

use App\Currency\CurrencyEnum;
use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Price\Downloader\TwelveData\Exception\TwelveDataInvalidValueException;
use App\Stock\Price\Downloader\TwelveData\TwelveDataDownloaderFacade;
use App\Stock\Price\StockAssetPriceRecord;
use App\Stock\Price\StockAssetPriceRecordRepository;
use App\System\SystemValueEnum;
use App\System\SystemValueFacade;
use App\Test\Unit\Stock\Support\StockAssetDataImportGuardStub;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\Json;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

class TwelveDataDownloaderFacadeTest extends TestCase
{

	use StockAssetDataImportGuardStub;

	public function testReturnsEmptyResultWithoutEligibleAssets(): void
	{
		$now = new ImmutableDateTime('2026-01-15 10:00:00');
		$stockAssetRepository = $this->createStub(StockAssetRepository::class);
		$stockAssetRepository->method('findAllByAssetPriceDownloader')->willReturn([]);
		$datetimeFactory = $this->createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);
		$clientFactory = $this->createMock(Psr18ClientFactory::class);
		$clientFactory->expects(self::never())->method('getClient');
		$entityManager = $this->createMock(EntityManagerInterface::class);
		$entityManager->expects(self::never())->method('flush');
		$systemValueFacade = $this->createMock(SystemValueFacade::class);
		$systemValueFacade->expects(self::never())->method('updateValue');

		$downloader = $this->createDownloader(
			$stockAssetRepository,
			$datetimeFactory,
			$clientFactory,
			entityManager: $entityManager,
			systemValueFacade: $systemValueFacade,
		);

		self::assertSame([], $downloader->getPriceForAssets());
	}

	public function testCreatesAndUpdatesRecordsFromBothResponseShapesAndSkipsUnknownTicker(): void
	{
		$today = new ImmutableDateTime('2026-01-15');
		$now = new ImmutableDateTime('2026-01-15 10:00:00');
		$firstAsset = $this->createMock(StockAsset::class);
		$firstAsset->method('getTicker')->willReturn('AAA');
		$firstAsset->method('getCurrency')->willReturn(CurrencyEnum::USD);
		$firstAsset->expects(self::once())
			->method('setCurrentPrice')
			->with(self::isInstanceOf(StockAssetPriceRecord::class), $now);
		$secondAsset = $this->createMock(StockAsset::class);
		$secondAsset->method('getTicker')->willReturn('BBB');
		$secondAsset->method('getCurrency')->willReturn(CurrencyEnum::EUR);
		$existingRecord = $this->createMock(StockAssetPriceRecord::class);
		$existingRecord->expects(self::once())->method('updatePrice')->with(67.89, $now);
		$secondAsset->expects(self::once())->method('setCurrentPrice')->with($existingRecord, $now);

		$stockAssetRepository = $this->createStub(StockAssetRepository::class);
		$stockAssetRepository->method('findAllByAssetPriceDownloader')->willReturn([$firstAsset, $secondAsset]);
		$priceRecordRepository = $this->createMock(StockAssetPriceRecordRepository::class);
		$priceRecordRepository->expects(self::exactly(2))
			->method('findByStockAssetAndDate')
			->willReturnCallback(static fn (StockAsset $asset): StockAssetPriceRecord|null =>
				$asset === $firstAsset ? null : $existingRecord);
		$datetimeFactory = $this->createStub(DatetimeFactory::class);
		$datetimeFactory->method('createToday')->willReturn($today);
		$datetimeFactory->method('createNow')->willReturn($now);

		$client = $this->createMock(ClientInterface::class);
		$client->expects(self::once())
			->method('sendRequest')
			->with(self::callback(static function (RequestInterface $request): bool {
				self::assertSame(
					'https://api.twelvedata.com/price?symbol=AAA,BBB&apikey=test-key',
					(string) $request->getUri(),
				);
				return true;
			}))
			->willReturn(new Response(200, [], Json::encode([
				'AAA' => '123.45',
				'BBB' => ['price' => '67.89'],
				'UNKNOWN' => '5.0',
			])));
		$clientFactory = $this->createStub(Psr18ClientFactory::class);
		$clientFactory->method('getClient')->willReturn($client);
		$entityManager = $this->createMock(EntityManagerInterface::class);
		$createdRecord = null;
		$entityManager->expects(self::once())
			->method('persist')
			->willReturnCallback(static function (object $record) use (&$createdRecord): void {
				$createdRecord = $record;
			});
		$entityManager->expects(self::once())->method('flush');
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('error')
			->with(self::stringContains('UNKNOWN'));
		$systemValueFacade = $this->createMock(SystemValueFacade::class);
		$systemValueFacade->expects(self::once())
			->method('updateValue')
			->with(SystemValueEnum::TWELVE_DATA_UPDATED_AT, $now);

		$downloader = $this->createDownloader(
			$stockAssetRepository,
			$datetimeFactory,
			$clientFactory,
			$priceRecordRepository,
			$entityManager,
			$logger,
			$systemValueFacade,
		);

		$result = $downloader->getPriceForAssets();

		self::assertCount(2, $result);
		self::assertInstanceOf(StockAssetPriceRecord::class, $createdRecord);
		self::assertSame(123.45, $result[0]->getPrice());
		self::assertSame($existingRecord, $result[1]);
	}

	public function testRejectsInvalidPriceValue(): void
	{
		$asset = $this->createStub(StockAsset::class);
		$asset->method('getTicker')->willReturn('AAA');
		$stockAssetRepository = $this->createStub(StockAssetRepository::class);
		$stockAssetRepository->method('findAllByAssetPriceDownloader')->willReturn([$asset]);
		$datetimeFactory = $this->createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn(new ImmutableDateTime('2026-01-15 10:00:00'));
		$datetimeFactory->method('createToday')->willReturn(new ImmutableDateTime('2026-01-15'));
		$client = $this->createStub(ClientInterface::class);
		$client->method('sendRequest')->willReturn(new Response(200, [], Json::encode([
			'price' => ['price' => 'invalid'],
		])));
		$clientFactory = $this->createStub(Psr18ClientFactory::class);
		$clientFactory->method('getClient')->willReturn($client);

		$downloader = $this->createDownloader($stockAssetRepository, $datetimeFactory, $clientFactory);

		$this->expectException(TwelveDataInvalidValueException::class);
		$downloader->getPriceForAssets();
	}

	private function createDownloader(
		StockAssetRepository $stockAssetRepository,
		DatetimeFactory $datetimeFactory,
		Psr18ClientFactory $clientFactory,
		StockAssetPriceRecordRepository|null $priceRecordRepository = null,
		EntityManagerInterface|null $entityManager = null,
		LoggerInterface|null $logger = null,
		SystemValueFacade|null $systemValueFacade = null,
	): TwelveDataDownloaderFacade
	{
		return new TwelveDataDownloaderFacade(
			'test-key',
			12,
			$stockAssetRepository,
			$priceRecordRepository ?? $this->createStub(StockAssetPriceRecordRepository::class),
			new Psr7RequestFactory(),
			$clientFactory,
			$datetimeFactory,
			$entityManager ?? $this->createStub(EntityManagerInterface::class),
			$logger ?? $this->createStub(LoggerInterface::class),
			$systemValueFacade ?? $this->createStub(SystemValueFacade::class),
			$this->createImportGuardStub(),
		);
	}

	public function testSelectedAssetBypassesBatchThresholdAndLeavesBatchStatisticsUntouched(): void
	{
		$asset = $this->createMock(StockAsset::class);
		$asset->method('getTicker')->willReturn('SELECTED');
		$asset->method('getCurrency')->willReturn(CurrencyEnum::USD);
		$asset->expects($this->once())->method('setCurrentPrice');
		$assets = $this->createMock(StockAssetRepository::class);
		$assets->expects($this->never())->method('findAllByAssetPriceDownloader');
		$now = new ImmutableDateTime('2026-09-24 12:00:00');
		$clock = $this->createStub(DatetimeFactory::class);
		$clock->method('createNow')->willReturn($now);
		$clock->method('createToday')->willReturn($now->setTime(0, 0));
		$client = $this->createMock(ClientInterface::class);
		$client->expects($this->once())->method('sendRequest')->with($this->callback(
			static fn (RequestInterface $request): bool => $request->getUri()->getQuery() === 'symbol=SELECTED&apikey=test-key',
		))->willReturn(new Response(200, [], '{"price":"125.50"}'));
		$clients = $this->createStub(Psr18ClientFactory::class);
		$clients->method('getClient')->willReturn($client);
		$stats = $this->createMock(SystemValueFacade::class);
		$stats->expects($this->never())->method('updateValue');
		$result = $this->createDownloader($assets, $clock, $clients, systemValueFacade: $stats)->getPriceForAssets(
			$asset,
		);
		$this->assertCount(1, $result);
		$this->assertSame(125.5, $result[0]->getAssetPrice()->getPrice());
	}

}
