<?php

declare(strict_types = 1);

namespace App\Test\Unit\Crypto\Price\Downloader;

use App\Crypto\Asset\CryptoAsset;
use App\Crypto\Asset\CryptoAssetRepository;
use App\Crypto\Price\CryptoAssetPriceRecord;
use App\Crypto\Price\CryptoAssetPriceRecordRepository;
use App\Crypto\Price\Downloader\TwelveData\TwelveDataCryptoDownloaderFacade;
use App\Currency\CurrencyEnum;
use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use App\System\SystemValueEnum;
use App\System\SystemValueFacade;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\Json;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

class TwelveDataCryptoDownloaderFacadeTest extends TestCase
{

	public function testUpdatesMonitoringWithoutCallingHttpWhenThereAreNoAssets(): void
	{
		$assetRepository = $this->createStub(CryptoAssetRepository::class);
		$assetRepository->method('findAll')->willReturn([]);
		$datetimeFactory = $this->createStub(DatetimeFactory::class);
		$datetimeFactory->method('createToday')->willReturn(new ImmutableDateTime('2026-01-15'));
		$datetimeFactory->method('createNow')->willReturn(new ImmutableDateTime('2026-01-15 10:00:00'));
		$clientFactory = $this->createMock(Psr18ClientFactory::class);
		$clientFactory->expects(self::never())->method('getClient');
		$systemValueFacade = $this->createMock(SystemValueFacade::class);
		$systemValueFacade->expects(self::once())
			->method('updateValue')
			->with(SystemValueEnum::CRYPTO_CURRENCY_DOWNLOADED_COUNT, null, 0);

		$downloader = $this->createDownloader(
			$assetRepository,
			$datetimeFactory,
			$clientFactory,
			systemValueFacade: $systemValueFacade,
		);

		self::assertSame([], $downloader->getPriceForAssets());
	}

	public function testCreatesRecordForNumericRateAndSkipsInvalidRate(): void
	{
		$today = new ImmutableDateTime('2026-01-15');
		$now = new ImmutableDateTime('2026-01-15 10:00:00');
		$bitcoin = $this->createMock(CryptoAsset::class);
		$bitcoin->method('getName')->willReturn('Bitcoin');
		$bitcoin->method('getTicker')->willReturn('BTC');
		$bitcoin->method('getCurrency')->willReturn(CurrencyEnum::USD);
		$bitcoin->expects(self::once())
			->method('setCurrentPrice')
			->with(self::isInstanceOf(CryptoAssetPriceRecord::class), $now);
		$ethereum = $this->createStub(CryptoAsset::class);
		$ethereum->method('getName')->willReturn('Ethereum');
		$ethereum->method('getTicker')->willReturn('ETH');
		$assetRepository = $this->createStub(CryptoAssetRepository::class);
		$assetRepository->method('findAll')->willReturn([$bitcoin, $ethereum]);
		$datetimeFactory = $this->createStub(DatetimeFactory::class);
		$datetimeFactory->method('createToday')->willReturn($today);
		$datetimeFactory->method('createNow')->willReturn($now);

		$client = $this->createMock(ClientInterface::class);
		$requestCount = 0;
		$client->expects(self::exactly(2))
			->method('sendRequest')
			->willReturnCallback(static function (RequestInterface $request) use (&$requestCount): Response {
				$requestCount++;
				if ($requestCount === 1) {
					self::assertSame(
						'https://api.twelvedata.com/exchange_rate?symbol=BTC/USD&apikey=test-key',
						(string) $request->getUri(),
					);
					return new Response(200, [], Json::encode(['rate' => '123.45']));
				}

				self::assertStringContainsString('symbol=ETH/USD', (string) $request->getUri());
				return new Response(200, [], Json::encode(['rate' => 'invalid']));
			});
		$clientFactory = $this->createStub(Psr18ClientFactory::class);
		$clientFactory->method('getClient')->willReturn($client);
		$priceRecordRepository = $this->createStub(CryptoAssetPriceRecordRepository::class);
		$priceRecordRepository->method('findByCryptoAssetAndDate')->willReturn(null);
		$entityManager = $this->createMock(EntityManagerInterface::class);
		$entityManager->expects(self::once())->method('persist');
		$entityManager->expects(self::once())->method('flush');
		$systemValueFacade = $this->createMock(SystemValueFacade::class);
		$systemValueFacade->expects(self::once())
			->method('updateValue')
			->with(SystemValueEnum::CRYPTO_CURRENCY_DOWNLOADED_COUNT, null, 1);

		$downloader = $this->createDownloader(
			$assetRepository,
			$datetimeFactory,
			$clientFactory,
			$priceRecordRepository,
			$entityManager,
			$systemValueFacade,
		);

		$result = $downloader->getPriceForAssets();

		self::assertCount(1, $result);
		self::assertSame(123.45, $result[0]->getPrice());
	}

	private function createDownloader(
		CryptoAssetRepository $assetRepository,
		DatetimeFactory $datetimeFactory,
		Psr18ClientFactory $clientFactory,
		CryptoAssetPriceRecordRepository|null $priceRecordRepository = null,
		EntityManagerInterface|null $entityManager = null,
		SystemValueFacade|null $systemValueFacade = null,
	): TwelveDataCryptoDownloaderFacade
	{
		return new TwelveDataCryptoDownloaderFacade(
			'test-key',
			$assetRepository,
			$priceRecordRepository ?? $this->createStub(CryptoAssetPriceRecordRepository::class),
			new Psr7RequestFactory(),
			$clientFactory,
			$datetimeFactory,
			$entityManager ?? $this->createStub(EntityManagerInterface::class),
			$this->createStub(LoggerInterface::class),
			$systemValueFacade ?? $this->createStub(SystemValueFacade::class),
		);
	}

}
