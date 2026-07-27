<?php

declare(strict_types = 1);

namespace App\Test\Unit\Crypto\Price\Downloader;

use App\Crypto\Asset\CryptoAsset;
use App\Crypto\Asset\CryptoAssetRepository;
use App\Crypto\Price\CryptoAssetPriceRecord;
use App\Crypto\Price\CryptoAssetPriceRecordRepository;
use App\Crypto\Price\Downloader\Json\CryptoAssetJsonDownloaderFacade;
use App\Crypto\Price\Downloader\Json\CryptoJsonDataSourceProviderFacade;
use App\Currency\CurrencyEnum;
use App\System\SystemValueEnum;
use App\System\SystemValueFacade;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class CryptoAssetJsonDownloaderFacadeTest extends TestCase
{

	private string $file;

	protected function setUp(): void
	{
		parent::setUp();

		$this->file = sys_get_temp_dir() . '/crypto-prices-' . Uuid::uuid4()->toString() . '.json';
	}

	protected function tearDown(): void
	{
		FileSystem::delete($this->file);

		parent::tearDown();
	}

	public function testReturnsEmptyResultAndLogsMissingFile(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('warning')
			->with('Crypto JSON file not found', ['file' => $this->file]);
		$datetimeFactory = $this->createMock(DatetimeFactory::class);
		$datetimeFactory->expects(self::never())->method('createToday');
		$systemValueFacade = $this->createMock(SystemValueFacade::class);
		$systemValueFacade->expects(self::never())->method('updateValue');

		$downloader = $this->createDownloader(
			$datetimeFactory,
			logger: $logger,
			systemValueFacade: $systemValueFacade,
		);

		self::assertSame([], $downloader->processResults());
	}

	public function testCreatesAndUpdatesKnownTickersAndSkipsMalformedRows(): void
	{
		FileSystem::write($this->file, Json::encode([
			['missingHtml' => true],
			['html' => <<<'HTML'
				<table>
				<tr data-testid-row="btc">
					<td><a href="/quote/BTC-USD/">BTC</a></td>
					<td><fin-streamer data-symbol="BTC-USD" data-field="regularMarketPrice" data-value="123.45"></fin-streamer></td>
				</tr>
				<tr data-testid-row="eth">
					<td><a href="/quote/ETH/">ETH</a></td>
					<td><fin-streamer data-symbol="ETH" data-field="regularMarketPrice" data-value="67.89"></fin-streamer></td>
				</tr>
				<tr data-testid-row="unknown">
					<td><a href="/quote/UNKNOWN-USD/">UNKNOWN</a></td>
					<td><fin-streamer data-symbol="UNKNOWN-USD" data-field="regularMarketPrice" data-value="5"></fin-streamer></td>
				</tr>
				<tr data-testid-row="missing-price"><td><a href="/quote/XRP-USD/">XRP</a></td></tr>
				</table>
				HTML],
		]));
		$today = new ImmutableDateTime('2026-01-15');
		$now = new ImmutableDateTime('2026-01-15 10:00:00');
		$bitcoin = $this->createMock(CryptoAsset::class);
		$bitcoin->method('getCurrency')->willReturn(CurrencyEnum::USD);
		$bitcoin->expects(self::once())
			->method('setCurrentPrice')
			->with(self::isInstanceOf(CryptoAssetPriceRecord::class), $now);
		$ethereum = $this->createMock(CryptoAsset::class);
		$ethereum->method('getCurrency')->willReturn(CurrencyEnum::USD);
		$existingRecord = $this->createMock(CryptoAssetPriceRecord::class);
		$existingRecord->expects(self::once())->method('updatePrice')->with(67.89, $now);
		$ethereum->expects(self::once())->method('setCurrentPrice')->with($existingRecord, $now);

		$assetRepository = $this->createMock(CryptoAssetRepository::class);
		$assetRepository->expects(self::exactly(3))
			->method('findByTicker')
			->willReturnCallback(static fn (string $ticker): CryptoAsset|null => match ($ticker) {
				'BTC' => $bitcoin,
				'ETH' => $ethereum,
				default => null,
			});
		$priceRecordRepository = $this->createMock(CryptoAssetPriceRecordRepository::class);
		$priceRecordRepository->expects(self::exactly(2))
			->method('findByCryptoAssetAndDate')
			->willReturnCallback(static fn (CryptoAsset $asset): CryptoAssetPriceRecord|null =>
				$asset === $bitcoin ? null : $existingRecord);
		$datetimeFactory = $this->createStub(DatetimeFactory::class);
		$datetimeFactory->method('createToday')->willReturn($today);
		$datetimeFactory->method('createNow')->willReturn($now);
		$entityManager = $this->createMock(EntityManagerInterface::class);
		$entityManager->expects(self::once())->method('persist');
		$entityManager->expects(self::once())->method('flush');
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::exactly(2))->method('info');
		$systemValueFacade = $this->createMock(SystemValueFacade::class);
		$systemValueFacade->expects(self::once())
			->method('updateValue')
			->with(SystemValueEnum::CRYPTO_CURRENCY_DOWNLOADED_COUNT, null, 2);

		$downloader = $this->createDownloader(
			$datetimeFactory,
			$priceRecordRepository,
			$assetRepository,
			$entityManager,
			$logger,
			$systemValueFacade,
		);

		$result = $downloader->processResults();

		self::assertCount(2, $result);
		self::assertSame(123.45, $result[0]->getPrice());
		self::assertSame($existingRecord, $result[1]);
	}

	private function createDownloader(
		DatetimeFactory $datetimeFactory,
		CryptoAssetPriceRecordRepository|null $priceRecordRepository = null,
		CryptoAssetRepository|null $assetRepository = null,
		EntityManagerInterface|null $entityManager = null,
		LoggerInterface|null $logger = null,
		SystemValueFacade|null $systemValueFacade = null,
	): CryptoAssetJsonDownloaderFacade
	{
		$dataSourceProvider = $this->createStub(CryptoJsonDataSourceProviderFacade::class);
		$dataSourceProvider->method('getCryptoTablePath')->willReturn($this->file);

		return new CryptoAssetJsonDownloaderFacade(
			$dataSourceProvider,
			$priceRecordRepository ?? $this->createStub(CryptoAssetPriceRecordRepository::class),
			$assetRepository ?? $this->createStub(CryptoAssetRepository::class),
			$entityManager ?? $this->createStub(EntityManagerInterface::class),
			$datetimeFactory,
			$logger ?? $this->createStub(LoggerInterface::class),
			$systemValueFacade ?? $this->createStub(SystemValueFacade::class),
		);
	}

}
