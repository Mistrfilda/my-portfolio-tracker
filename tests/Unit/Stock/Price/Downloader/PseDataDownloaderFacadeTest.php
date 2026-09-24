<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Price\Downloader;

use App\Currency\CurrencyEnum;
use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Price\Downloader\Pse\Exception\PseInvalidResponseException;
use App\Stock\Price\Downloader\Pse\Exception\PseMissingStockAssetIsinException;
use App\Stock\Price\Downloader\Pse\PseDataDownloaderFacade;
use App\Stock\Price\StockAssetPriceRecord;
use App\Stock\Price\StockAssetPriceRecordRepository;
use App\System\SystemValueEnum;
use App\System\SystemValueFacade;
use App\Test\Unit\Stock\Support\StockAssetDataImportGuardStub;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class PseDataDownloaderFacadeTest extends TestCase
{

	use StockAssetDataImportGuardStub;

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

	#[TestWith([false])]
	#[TestWith([true])]
	public function testCreatesPriceRecordFromExchangeTable(bool $selected): void
	{
		$today = new ImmutableDateTime('2026-01-15');
		$now = new ImmutableDateTime('2026-01-15 10:00:00');
		$asset = $this->createMock(StockAsset::class);
		$asset->method('getIsin')->willReturn('CZ0000000001');
		$asset->method('getCurrency')->willReturn(CurrencyEnum::CZK);
		$asset->method('getId')->willReturn(Uuid::uuid4());
		$asset->method('getName')->willReturn('Prague Stock');
		$asset->expects(self::once())
			->method('setCurrentPrice')
			->with(self::isInstanceOf(StockAssetPriceRecord::class), $now);
		$assetRepository = $this->createMock(StockAssetRepository::class);
		$assetRepository->expects($this->exactly($selected ? 0 : 1))->method(
			'findAllByAssetPriceDownloader',
		)->willReturn(
			[$asset],
		);
		$priceRecordRepository = $this->createStub(StockAssetPriceRecordRepository::class);
		$priceRecordRepository->method('findByStockAssetAndDate')->willReturn(null);
		$datetimeFactory = $this->createStub(DatetimeFactory::class);
		$datetimeFactory->method('createToday')->willReturn($today);
		$datetimeFactory->method('createNow')->willReturn($now);

		$html = <<<'HTML'
			<html lang="en"><body><div class="stock-table"><table><tbody><tr>
			<td><div class="isin">CZ0000000001</div></td>
			<td data-value="123.45">123.45</td>
			</tr></tbody></table></div></body></html>
			HTML;
		$client = $this->createStub(ClientInterface::class);
		$client->method('sendRequest')->willReturn(new Response(200, [], $html));
		$clientFactory = $this->createMock(Psr18ClientFactory::class);
		$clientFactory->expects(self::once())
			->method('getClient')
			->with(['verify' => false])
			->willReturn($client);
		$entityManager = $this->createMock(EntityManagerInterface::class);
		$entityManager->expects(self::once())->method('persist');
		$entityManager->expects(self::once())->method('flush');
		$systemValueFacade = $this->createMock(SystemValueFacade::class);
		$systemValueFacade->expects($this->exactly($selected ? 0 : 1))
			->method('updateValue')
			->with(SystemValueEnum::PSE_DATA_UPDATED_AT, $now);

		$result = $this->createDownloader(
			$assetRepository,
			$datetimeFactory,
			$clientFactory,
			[[
				'url' => 'https://example.test/pse',
				'pricePositionTag' => 1,
				'tableTdsCount' => 2,
			]],
			$priceRecordRepository,
			$entityManager,
			systemValueFacade: $systemValueFacade,
		)->getPriceForAssets($selected ? $asset : null);

		self::assertCount(1, $result);
		self::assertSame(123.45, $result[0]->getPrice());
	}

	public function testRejectsResponseWithoutHtmlBody(): void
	{
		$asset = $this->createStub(StockAsset::class);
		$assetRepository = $this->createStub(StockAssetRepository::class);
		$assetRepository->method('findAllByAssetPriceDownloader')->willReturn([$asset]);
		$datetimeFactory = $this->createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn(new ImmutableDateTime('2026-01-15'));
		$client = $this->createStub(ClientInterface::class);
		$client->method('sendRequest')->willReturn(new Response(200, [], 'invalid response'));
		$clientFactory = $this->createStub(Psr18ClientFactory::class);
		$clientFactory->method('getClient')->willReturn($client);

		$downloader = $this->createDownloader(
			$assetRepository,
			$datetimeFactory,
			$clientFactory,
			[['url' => 'https://example.test/pse', 'pricePositionTag' => 1, 'tableTdsCount' => 2]],
		);

		$this->expectException(PseInvalidResponseException::class);
		$downloader->getPriceForAssets();
	}

	public function testRejectsAssetWithoutIsinBeforeCreatingRecord(): void
	{
		$asset = $this->createStub(StockAsset::class);
		$asset->method('getIsin')->willReturn(null);
		$assetRepository = $this->createStub(StockAssetRepository::class);
		$assetRepository->method('findAllByAssetPriceDownloader')->willReturn([$asset]);
		$datetimeFactory = $this->createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn(new ImmutableDateTime('2026-01-15'));
		$datetimeFactory->method('createToday')->willReturn(new ImmutableDateTime('2026-01-15'));
		$clientFactory = $this->createStub(Psr18ClientFactory::class);
		$clientFactory->method('getClient')->willReturn($this->createStub(ClientInterface::class));

		$downloader = $this->createDownloader($assetRepository, $datetimeFactory, $clientFactory, []);

		$this->expectException(PseMissingStockAssetIsinException::class);
		$downloader->getPriceForAssets();
	}

	/**
	 * @param array<array{url: string, pricePositionTag: int, tableTdsCount: int}> $requests
	 */
	private function createDownloader(
		StockAssetRepository $assetRepository,
		DatetimeFactory $datetimeFactory,
		Psr18ClientFactory $clientFactory,
		array $requests = [],
		StockAssetPriceRecordRepository|null $priceRecordRepository = null,
		EntityManagerInterface|null $entityManager = null,
		LoggerInterface|null $logger = null,
		SystemValueFacade|null $systemValueFacade = null,
	): PseDataDownloaderFacade
	{
		return new PseDataDownloaderFacade(
			false,
			12,
			$requests,
			$assetRepository,
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

}
