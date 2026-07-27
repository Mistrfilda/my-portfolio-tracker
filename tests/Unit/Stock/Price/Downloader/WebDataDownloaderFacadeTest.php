<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Price\Downloader;

use App\Currency\CurrencyEnum;
use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Price\Downloader\Web\WebDataDownloaderFacade;
use App\Stock\Price\StockAssetPriceRecord;
use App\Stock\Price\StockAssetPriceRecordRepository;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

class WebDataDownloaderFacadeTest extends TestCase
{

	public function testReturnsEmptyResultWithoutEligibleAssets(): void
	{
		$assetRepository = $this->createStub(StockAssetRepository::class);
		$assetRepository->method('findAllByAssetPriceDownloader')->willReturn([]);
		$datetimeFactory = $this->createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn(new ImmutableDateTime('2026-01-15'));
		$clientFactory = $this->createMock(Psr18ClientFactory::class);
		$clientFactory->expects(self::never())->method('getClient');

		self::assertSame([], $this->createDownloader(
			$assetRepository,
			$datetimeFactory,
			$clientFactory,
		)->getPriceForAssets());
	}

	public function testUpdatesExistingRecordAndConvertsGbpPenceToPounds(): void
	{
		$today = new ImmutableDateTime('2026-01-15');
		$now = new ImmutableDateTime('2026-01-15 10:00:00');
		$asset = $this->createMock(StockAsset::class);
		$asset->method('getName')->willReturn('London Stock');
		$asset->method('getTicker')->willReturn('LON');
		$asset->method('getCurrency')->willReturn(CurrencyEnum::GBP);
		$existingRecord = $this->createMock(StockAssetPriceRecord::class);
		$existingRecord->expects(self::once())->method('updatePrice')->with(12.345, $now);
		$asset->expects(self::once())->method('setCurrentPrice')->with($existingRecord, $now);
		$assetRepository = $this->createStub(StockAssetRepository::class);
		$assetRepository->method('findAllByAssetPriceDownloader')->willReturn([$asset]);
		$priceRecordRepository = $this->createMock(StockAssetPriceRecordRepository::class);
		$priceRecordRepository->expects(self::once())
			->method('findByStockAssetAndDate')
			->with($asset, $today)
			->willReturn($existingRecord);
		$datetimeFactory = $this->createStub(DatetimeFactory::class);
		$datetimeFactory->method('createToday')->willReturn($today);
		$datetimeFactory->method('createNow')->willReturn($now);

		$html = <<<'HTML'
			<html lang="en"><body><section data-testid="quote-price">
			<fin-streamer data-field="regularMarketPrice">1,234.50</fin-streamer>
			</section></body></html>
			HTML;
		$client = $this->createMock(ClientInterface::class);
		$client->expects(self::once())
			->method('sendRequest')
			->with(self::callback(static function (RequestInterface $request): bool {
				self::assertSame('https://example.test/LON', (string) $request->getUri());
				self::assertSame('example.test', $request->getHeaderLine('Host'));
				self::assertSame('test-cookie', $request->getHeaderLine('Cookie'));
				return true;
			}))
			->willReturn(new Response(200, [], $html));
		$clientFactory = $this->createMock(Psr18ClientFactory::class);
		$clientFactory->expects(self::once())
			->method('getClient')
			->with(['verify' => false])
			->willReturn($client);
		$entityManager = $this->createMock(EntityManagerInterface::class);
		$entityManager->expects(self::never())->method('persist');
		$entityManager->expects(self::once())->method('flush');

		$result = $this->createDownloader(
			$assetRepository,
			$datetimeFactory,
			$clientFactory,
			$priceRecordRepository,
			$entityManager,
		)->getPriceForAssets();

		self::assertSame([$existingRecord], $result);
	}

	private function createDownloader(
		StockAssetRepository $assetRepository,
		DatetimeFactory $datetimeFactory,
		Psr18ClientFactory $clientFactory,
		StockAssetPriceRecordRepository|null $priceRecordRepository = null,
		EntityManagerInterface|null $entityManager = null,
	): WebDataDownloaderFacade
	{
		return new WebDataDownloaderFacade(
			'https://example.test/%s',
			'example.test',
			'test-cookie',
			false,
			12,
			new Psr7RequestFactory(),
			$clientFactory,
			$assetRepository,
			$datetimeFactory,
			$priceRecordRepository ?? $this->createStub(StockAssetPriceRecordRepository::class),
			$entityManager ?? $this->createStub(EntityManagerInterface::class),
			$this->createStub(LoggerInterface::class),
		);
	}

}
