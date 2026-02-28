<?php

declare(strict_types = 1);

namespace App\Test\Integration\Stock;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\Exception\StockAssetTickerAlreadyExistsException;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Asset\StockAssetFacade;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\StockAssetDividendSourceEnum;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Test\Integration\IntegrationTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class StockAssetFacadeTest extends IntegrationTestCase
{

	private StockAssetFacade $stockAssetFacade;

	private StockAssetRepository $stockAssetRepository;

	protected function setUp(): void
	{
		parent::setUp();

		$this->stockAssetFacade = $this->getService(StockAssetFacade::class);
		$this->stockAssetRepository = $this->getService(StockAssetRepository::class);

		$this->mockCurrentAppAdmin();
	}

	public function testCreate(): void
	{
		$ticker = 'FACADE-CREATE-' . bin2hex(random_bytes(4));

		$stockAsset = $this->stockAssetFacade->create(
			'Test Company',
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			$ticker,
			StockAssetExchange::NYSE,
			CurrencyEnum::USD,
			'US1234567890',
			StockAssetDividendSourceEnum::WEB,
			15.0,
			CurrencyEnum::CZK,
			true,
			false,
			false,
			null,
		);

		$this->assertSame('Test Company', $stockAsset->getName());
		$this->assertSame($ticker, $stockAsset->getTicker());
		$this->assertSame(StockAssetExchange::NYSE, $stockAsset->getExchange());
		$this->assertSame(CurrencyEnum::USD, $stockAsset->getCurrency());
		$this->assertSame('US1234567890', $stockAsset->getIsin());
		$this->assertSame(StockAssetDividendSourceEnum::WEB, $stockAsset->getStockAssetDividendSource());
		$this->assertSame(15.0, $stockAsset->getDividendTax());
		$this->assertSame(CurrencyEnum::CZK, $stockAsset->getBrokerDividendCurrency());

		$foundAsset = $this->stockAssetRepository->findByTicker($ticker);
		$this->assertNotNull($foundAsset);
		$this->assertSame($stockAsset->getId()->toString(), $foundAsset->getId()->toString());
	}

	public function testCreateThrowsExceptionForDuplicateTicker(): void
	{
		$ticker = 'FACADE-DUP-' . bin2hex(random_bytes(4));

		$this->stockAssetFacade->create(
			'First Company',
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			$ticker,
			StockAssetExchange::NYSE,
			CurrencyEnum::USD,
			null,
			null,
			null,
			null,
			true,
			false,
			false,
			null,
		);

		$this->expectException(StockAssetTickerAlreadyExistsException::class);

		$this->stockAssetFacade->create(
			'Second Company',
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			$ticker,
			StockAssetExchange::NASDAQ,
			CurrencyEnum::EUR,
			null,
			null,
			null,
			null,
			true,
			false,
			false,
			null,
		);
	}

	public function testUpdate(): void
	{
		$ticker = 'FACADE-UPD-' . bin2hex(random_bytes(4));

		$stockAsset = $this->stockAssetFacade->create(
			'Original Name',
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			$ticker,
			StockAssetExchange::NYSE,
			CurrencyEnum::USD,
			null,
			null,
			null,
			null,
			true,
			false,
			false,
			null,
		);

		$updatedAsset = $this->stockAssetFacade->update(
			$stockAsset->getId(),
			'Updated Name',
			StockAssetPriceDownloaderEnum::WEB_SCRAP,
			$ticker,
			StockAssetExchange::NASDAQ,
			CurrencyEnum::EUR,
			'EU9876543210',
			StockAssetDividendSourceEnum::MANUAL,
			10.0,
			CurrencyEnum::USD,
			false,
			true,
			true,
			null,
		);

		$this->assertSame('Updated Name', $updatedAsset->getName());
		$this->assertSame(StockAssetPriceDownloaderEnum::WEB_SCRAP, $updatedAsset->getAssetPriceDownloader());
		$this->assertSame(StockAssetExchange::NASDAQ, $updatedAsset->getExchange());
		$this->assertSame(CurrencyEnum::EUR, $updatedAsset->getCurrency());
		$this->assertSame('EU9876543210', $updatedAsset->getIsin());
		$this->assertSame(StockAssetDividendSourceEnum::MANUAL, $updatedAsset->getStockAssetDividendSource());
		$this->assertSame(10.0, $updatedAsset->getDividendTax());
		$this->assertSame(CurrencyEnum::USD, $updatedAsset->getBrokerDividendCurrency());
		$this->assertTrue($updatedAsset->isWatchlist());
	}

	public function testCreateWithNullOptionalFields(): void
	{
		$ticker = 'FACADE-MIN-' . bin2hex(random_bytes(4));

		$stockAsset = $this->stockAssetFacade->create(
			'Minimal Company',
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			$ticker,
			StockAssetExchange::NASDAQ,
			CurrencyEnum::EUR,
			null,
			null,
			null,
			null,
			false,
			false,
			false,
			null,
		);

		$this->assertSame('Minimal Company', $stockAsset->getName());
		$this->assertNull($stockAsset->getIsin());
		$this->assertNull($stockAsset->getStockAssetDividendSource());
		$this->assertNull($stockAsset->getDividendTax());
		$this->assertNull($stockAsset->getBrokerDividendCurrency());
		$this->assertFalse($stockAsset->isWatchlist());
	}

}
