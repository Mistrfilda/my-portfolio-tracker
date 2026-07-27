<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock;

use App\Currency\CurrencyEnum;
use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\Downloader\WebStockAssetDividendDownloaderFacade;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Dividend\StockAssetDividendRepository;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

class WebStockAssetDividendDownloaderFacadeTest extends TestCase
{

	public function testDoesNotCallHttpWithoutDividendAssets(): void
	{
		$assetRepository = $this->createStub(StockAssetRepository::class);
		$assetRepository->method('findByStockAssetDividendSource')->willReturn([]);
		$datetimeFactory = $this->createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn(new ImmutableDateTime('2026-01-15'));
		$clientFactory = $this->createMock(Psr18ClientFactory::class);
		$clientFactory->expects(self::never())->method('getClient');
		$entityManager = $this->createMock(EntityManagerInterface::class);
		$entityManager->expects(self::never())->method('flush');

		$this->createDownloader(
			$assetRepository,
			$datetimeFactory,
			$clientFactory,
			entityManager: $entityManager,
		)->downloadDividendRecords();
	}

	public function testImportsNewDividendSkipsDuplicateAndConvertsGbpPence(): void
	{
		$today = new ImmutableDateTime('2026-03-01');
		$now = new ImmutableDateTime('2026-03-01 10:00:00');
		$asset = $this->createStub(StockAsset::class);
		$asset->method('getName')->willReturn('London Stock');
		$asset->method('getTicker')->willReturn('LON');
		$asset->method('getCurrency')->willReturn(CurrencyEnum::GBP);
		$assetRepository = $this->createStub(StockAssetRepository::class);
		$assetRepository->method('findByStockAssetDividendSource')->willReturn([$asset]);
		$datetimeFactory = $this->createStub(DatetimeFactory::class);
		$datetimeFactory->method('createToday')->willReturn($today);
		$datetimeFactory->method('createNow')->willReturn($now);

		$html = <<<'HTML'
			<html lang="en"><body><div class="table-container"><table><tbody>
			<tr><td>Jan 15, 2026</td><td>£123.40</td></tr>
			<tr><td>Feb 15, 2026</td><td>£250.00</td></tr>
			</tbody></table></div></body></html>
			HTML;
		$client = $this->createMock(ClientInterface::class);
		$client->expects(self::once())
			->method('sendRequest')
			->with(self::callback(static function (RequestInterface $request): bool {
				self::assertStringStartsWith('https://example.test/LON?', (string) $request->getUri());
				self::assertSame('example.test', $request->getHeaderLine('Host'));
				self::assertSame('test-cookie', $request->getHeaderLine('Cookie'));
				return true;
			}))
			->willReturn(new Response(200, [], $html));
		$clientFactory = $this->createStub(Psr18ClientFactory::class);
		$clientFactory->method('getClient')->willReturn($client);

		$existingDividend = $this->createStub(StockAssetDividend::class);
		$dividendRepository = $this->createMock(StockAssetDividendRepository::class);
		$dividendRepository->expects(self::exactly(2))
			->method('findOneByStockAssetExDate')
			->willReturnCallback(static fn (
				StockAsset $actualAsset,
				ImmutableDateTime $date,
			): StockAssetDividend|null => $date->format('Y-m-d') === '2026-01-15' ? $existingDividend : null);
		$entityManager = $this->createMock(EntityManagerInterface::class);
		$createdDividend = null;
		$entityManager->expects(self::once())
			->method('persist')
			->willReturnCallback(static function (object $entity) use (&$createdDividend): void {
				$createdDividend = $entity;
			});
		$entityManager->expects(self::once())->method('flush');

		$this->createDownloader(
			$assetRepository,
			$datetimeFactory,
			$clientFactory,
			$dividendRepository,
			$entityManager,
		)->downloadDividendRecords();

		self::assertInstanceOf(StockAssetDividend::class, $createdDividend);
		self::assertSame('2026-02-15', $createdDividend->getExDate()->format('Y-m-d'));
		self::assertNull($createdDividend->getPaymentDate());
		self::assertSame('2026-02-15', $createdDividend->getDeclarationDate()?->format('Y-m-d'));
		self::assertSame(CurrencyEnum::GBP, $createdDividend->getCurrency());
		self::assertSame(2.5, $createdDividend->getAmount());
	}

	private function createDownloader(
		StockAssetRepository $assetRepository,
		DatetimeFactory $datetimeFactory,
		Psr18ClientFactory $clientFactory,
		StockAssetDividendRepository|null $dividendRepository = null,
		EntityManagerInterface|null $entityManager = null,
	): WebStockAssetDividendDownloaderFacade
	{
		return new WebStockAssetDividendDownloaderFacade(
			'https://example.test/%s?from=%d&type=%s',
			'example.test',
			'test-cookie',
			new Psr7RequestFactory(),
			$clientFactory,
			$assetRepository,
			$dividendRepository ?? $this->createStub(StockAssetDividendRepository::class),
			$datetimeFactory,
			$entityManager ?? $this->createStub(EntityManagerInterface::class),
			$this->createStub(LoggerInterface::class),
		);
	}

}
