<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock;

use App\Admin\CurrentAppAdminGetter;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\Industry\StockAssetIndustryRepository;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Asset\StockAssetFacade;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\StockAssetDividendSourceEnum;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Test\UpdatedTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class StockAssetFacadeTest extends UpdatedTestCase
{

	private StockAssetFacade|MockObject $stockAssetFacade;

	private StockAssetRepository|MockObject $stockAssetRepository;

	private EntityManagerInterface|MockObject $entityManager;

	private DatetimeFactory|MockObject $datetimeFactory;

	private LoggerInterface|MockObject $logger;

	private CurrentAppAdminGetter|MockObject $currentAppAdminGetter;

	private StockAssetIndustryRepository|MockObject $stockAssetIndustryRepository;

	protected function setUp(): void
	{
		$this->stockAssetRepository = $this->createMock(StockAssetRepository::class);
		$this->entityManager = $this->createMock(EntityManagerInterface::class);
		$this->datetimeFactory = $this->createMock(DatetimeFactory::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->currentAppAdminGetter = $this->createMock(CurrentAppAdminGetter::class);
		$this->stockAssetIndustryRepository = $this->createMock(StockAssetIndustryRepository::class);

		$this->stockAssetFacade = new StockAssetFacade(
			$this->stockAssetRepository,
			$this->entityManager,
			$this->datetimeFactory,
			$this->logger,
			$this->currentAppAdminGetter,
			$this->stockAssetIndustryRepository,
		);
	}

	public function testUpdate(): void
	{
		$name = 'StockAssetName';
		$assetPriceDownloader = StockAssetPriceDownloaderEnum::PRAGUE_EXCHANGE_DOWNLOADER;
		$ticker = 'Ticker';
		$exchange = StockAssetExchange::PRAGUE_STOCK_EXCHANGE;
		$currency = CurrencyEnum::CZK;
		$isin = 'ISIN';
		$stockAssetDividendSource = StockAssetDividendSourceEnum::WEB;
		$dividendTax = 15.15;
		$brokerDividendCurrency = CurrencyEnum::USD;

		$stockAsset = $this->stockAssetFacade->create(
			$name,
			$assetPriceDownloader,
			$ticker,
			$exchange,
			$currency,
			$isin,
			$stockAssetDividendSource,
			$dividendTax,
			$brokerDividendCurrency,
			true,
			true,
			null,
		);

		self::assertSame($name, $stockAsset->getName());
		self::assertSame($assetPriceDownloader, $stockAsset->getAssetPriceDownloader());
		self::assertSame($ticker, $stockAsset->getTicker());
		self::assertSame($exchange, $stockAsset->getExchange());
		self::assertSame($currency, $stockAsset->getCurrency());
		self::assertNotNull($stockAsset->getIsin());
		self::assertSame($isin, $stockAsset->getIsin());
		self::assertNotNull($stockAsset->getStockAssetDividendSource());
		self::assertSame($stockAssetDividendSource, $stockAsset->getStockAssetDividendSource());
		self::assertNotNull($stockAsset->getDividendTax());
		self::assertSame($dividendTax, $stockAsset->getDividendTax());
		self::assertNotNull($stockAsset->getBrokerDividendCurrency());
		self::assertSame($brokerDividendCurrency, $stockAsset->getBrokerDividendCurrency());
	}

}
