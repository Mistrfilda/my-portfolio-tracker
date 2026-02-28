<?php

declare(strict_types = 1);

namespace App\Test\Integration\Stock;

use App\Currency\CurrencyEnum;
use App\Doctrine\NoEntityFoundException;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\StockAssetDividendSourceEnum;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Test\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

class StockAssetRepositoryTest extends IntegrationTestCase
{

	private StockAssetRepository $stockAssetRepository;

	private EntityManagerInterface $entityManager;

	protected function setUp(): void
	{
		parent::setUp();

		$this->stockAssetRepository = $this->getService(StockAssetRepository::class);
		$this->entityManager = $this->getService(EntityManagerInterface::class);
	}

	public function testFindByTicker(): void
	{
		$stockAsset = $this->createStockAsset('Apple Inc.', 'AAPL', StockAssetExchange::NASDAQ);
		$this->entityManager->persist($stockAsset);
		$this->entityManager->flush();

		$result = $this->stockAssetRepository->findByTicker('AAPL');

		$this->assertNotNull($result);
		$this->assertSame('AAPL', $result->getTicker());
		$this->assertSame('Apple Inc.', $result->getName());
	}

	public function testFindByTickerReturnsNullWhenNotFound(): void
	{
		$result = $this->stockAssetRepository->findByTicker('NONEXISTENT');

		$this->assertNull($result);
	}

	public function testGetById(): void
	{
		$stockAsset = $this->createStockAsset('Microsoft Corp.', 'MSFT', StockAssetExchange::NASDAQ);
		$this->entityManager->persist($stockAsset);
		$this->entityManager->flush();

		$result = $this->stockAssetRepository->getById($stockAsset->getId());

		$this->assertSame($stockAsset->getId()->toString(), $result->getId()->toString());
		$this->assertSame('Microsoft Corp.', $result->getName());
	}

	public function testGetByIdThrowsExceptionWhenNotFound(): void
	{
		$this->expectException(NoEntityFoundException::class);

		$this->stockAssetRepository->getById(Uuid::uuid4());
	}

	public function testFindAll(): void
	{
		$apple = $this->createStockAsset('Apple Inc.', 'AAPL-ALL', StockAssetExchange::NASDAQ);
		$microsoft = $this->createStockAsset('Microsoft Corp.', 'MSFT-ALL', StockAssetExchange::NASDAQ);

		$this->entityManager->persist($apple);
		$this->entityManager->persist($microsoft);
		$this->entityManager->flush();

		$result = $this->stockAssetRepository->findAll();

		$tickers = array_map(
			static fn (StockAsset $asset): string => $asset->getTicker(),
			$result,
		);

		$this->assertContains('AAPL-ALL', $tickers);
		$this->assertContains('MSFT-ALL', $tickers);
	}

	public function testGetAllActiveAssets(): void
	{
		$active = $this->createStockAsset(
			'Active Stock',
			'ACTIVE-1',
			StockAssetExchange::NYSE,
			shouldDownloadPrice: true,
		);

		$inactive = $this->createStockAsset(
			'Inactive Stock',
			'INACTIVE-1',
			StockAssetExchange::NYSE,
			shouldDownloadPrice: false,
		);

		$this->entityManager->persist($active);
		$this->entityManager->persist($inactive);
		$this->entityManager->flush();

		$result = $this->stockAssetRepository->getAllActiveAssets();

		$tickers = array_map(
			static fn (StockAsset $asset): string => $asset->getTicker(),
			$result,
		);

		$this->assertContains('ACTIVE-1', $tickers);
		$this->assertNotContains('INACTIVE-1', $tickers);
	}

	public function testFindAllByAssetPriceDownloader(): void
	{
		$twelveData = $this->createStockAsset(
			'Twelve Data Stock',
			'TD-1',
			StockAssetExchange::NYSE,
			assetPriceDownloader: StockAssetPriceDownloaderEnum::TWELVE_DATA,
		);

		$webScrap = $this->createStockAsset(
			'Web Scrap Stock',
			'WS-1',
			StockAssetExchange::NYSE,
			assetPriceDownloader: StockAssetPriceDownloaderEnum::WEB_SCRAP,
		);

		$this->entityManager->persist($twelveData);
		$this->entityManager->persist($webScrap);
		$this->entityManager->flush();

		$result = $this->stockAssetRepository->findAllByAssetPriceDownloader(
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
		);

		$tickers = array_map(
			static fn (StockAsset $asset): string => $asset->getTicker(),
			$result,
		);

		$this->assertContains('TD-1', $tickers);
		$this->assertNotContains('WS-1', $tickers);
	}

	public function testGetEnabledCount(): void
	{
		$countBefore = $this->stockAssetRepository->getEnabledCount();

		$asset = $this->createStockAsset(
			'Enabled Stock',
			'EN-CNT-1',
			StockAssetExchange::NYSE,
			shouldDownloadPrice: true,
		);

		$this->entityManager->persist($asset);
		$this->entityManager->flush();

		$countAfter = $this->stockAssetRepository->getEnabledCount();
		$this->assertSame($countBefore + 1, $countAfter);
	}

	public function testFindByStockAssetDividendSource(): void
	{
		$webSource = $this->createStockAsset(
			'Web Dividend Stock',
			'WD-1',
			StockAssetExchange::NYSE,
			stockAssetDividendSource: StockAssetDividendSourceEnum::WEB,
		);

		$manualSource = $this->createStockAsset(
			'Manual Dividend Stock',
			'MD-1',
			StockAssetExchange::NYSE,
			stockAssetDividendSource: StockAssetDividendSourceEnum::MANUAL,
			shouldDownloadPrice: false,
		);

		$this->entityManager->persist($webSource);
		$this->entityManager->persist($manualSource);
		$this->entityManager->flush();

		$result = $this->stockAssetRepository->findByStockAssetDividendSource(
			StockAssetDividendSourceEnum::WEB,
		);

		$tickers = array_map(
			static fn (StockAsset $asset): string => $asset->getTicker(),
			$result,
		);

		$this->assertContains('WD-1', $tickers);
		$this->assertNotContains('MD-1', $tickers);
	}

	private function createStockAsset(
		string $name,
		string $ticker,
		StockAssetExchange $exchange,
		StockAssetPriceDownloaderEnum $assetPriceDownloader = StockAssetPriceDownloaderEnum::TWELVE_DATA,
		CurrencyEnum $currency = CurrencyEnum::USD,
		StockAssetDividendSourceEnum|null $stockAssetDividendSource = null,
		bool $shouldDownloadPrice = true,
	): StockAsset
	{
		return new StockAsset(
			$name,
			$assetPriceDownloader,
			$ticker,
			$exchange,
			$currency,
			new ImmutableDateTime(),
			isin: null,
			stockAssetDividendSource: $stockAssetDividendSource,
			dividendTax: null,
			brokerDividendCurrency: null,
			shouldDownloadPrice: $shouldDownloadPrice,
			shouldDownloadValuation: false,
			watchlist: false,
			industry: null,
		);
	}

}
