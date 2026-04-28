<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Dividend\Safety;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Dividend\Safety\StockDividendSafetyScoreProvider;
use App\Stock\Dividend\Safety\StockDividendSafetyScoreStatusEnum;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Dividend\StockAssetDividendRepository;
use App\Stock\Dividend\StockAssetDividendTypeEnum;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Valuation\Data\StockValuationData;
use App\Stock\Valuation\Data\StockValuationDataRepository;
use App\Stock\Valuation\StockValuationTypeEnum;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class StockDividendSafetyScoreProviderTest extends TestCase
{

	public function testReturnsSafeScoreForHealthyDividendStock(): void
	{
		$stockAsset = $this->createStockAsset();
		$provider = $this->createProvider(
			$stockAsset,
			[
				StockValuationTypeEnum::PAYOUT_RATIO->value => 55.0,
				StockValuationTypeEnum::LEVERED_FREE_CASH_FLOW->value => 100_000_000.0,
				StockValuationTypeEnum::TOTAL_DEBT_EQUITY->value => 70.0,
				StockValuationTypeEnum::QUARTERLY_EARNINGS_GROWTH->value => 8.0,
			],
			[
				$this->createDividend($stockAsset, '2024-03-01', 2.0),
				$this->createDividend($stockAsset, '2025-03-01', 2.2),
			],
		);

		$score = $provider->getForStockAsset($stockAsset);

		self::assertSame(100, $score->getScore());
		self::assertSame(StockDividendSafetyScoreStatusEnum::SAFE, $score->getStatus());
	}

	public function testReturnsRiskyScoreForWeakDividendStock(): void
	{
		$stockAsset = $this->createStockAsset();
		$provider = $this->createProvider(
			$stockAsset,
			[
				StockValuationTypeEnum::PAYOUT_RATIO->value => 120.0,
				StockValuationTypeEnum::LEVERED_FREE_CASH_FLOW->value => -100_000_000.0,
				StockValuationTypeEnum::TOTAL_DEBT_EQUITY->value => 250.0,
				StockValuationTypeEnum::QUARTERLY_EARNINGS_GROWTH->value => -25.0,
			],
			[
				$this->createDividend($stockAsset, '2024-03-01', 2.0),
				$this->createDividend($stockAsset, '2025-03-01', 1.0),
			],
		);

		$score = $provider->getForStockAsset($stockAsset);

		self::assertSame(0, $score->getScore());
		self::assertSame(StockDividendSafetyScoreStatusEnum::RISKY, $score->getStatus());
	}

	public function testReturnsWatchScoreWhenImportantMetricsAreMissing(): void
	{
		$stockAsset = $this->createStockAsset();
		$provider = $this->createProvider($stockAsset, [], []);

		$score = $provider->getForStockAsset($stockAsset);

		self::assertSame(69, $score->getScore());
		self::assertSame(StockDividendSafetyScoreStatusEnum::WATCH, $score->getStatus());
	}

	/**
	 * @param array<string, float> $valuationValues
	 * @param array<StockAssetDividend> $dividends
	 */
	private function createProvider(
		StockAsset $stockAsset,
		array $valuationValues,
		array $dividends,
	): StockDividendSafetyScoreProvider
	{
		$valuations = [];
		foreach ($valuationValues as $typeValue => $value) {
			$type = StockValuationTypeEnum::from($typeValue);
			$valuations[$type->value] = $this->createValuationData($stockAsset, $type, $value);
		}

		$stockValuationDataRepository = $this->createMock(StockValuationDataRepository::class);
		$stockValuationDataRepository->method('findTypesLatestForStockAsset')->willReturn($valuations);

		$stockAssetDividendRepository = $this->createMock(StockAssetDividendRepository::class);
		$stockAssetDividendRepository->method('findByStockAsset')->willReturn($dividends);

		return new StockDividendSafetyScoreProvider($stockValuationDataRepository, $stockAssetDividendRepository);
	}

	private function createValuationData(
		StockAsset $stockAsset,
		StockValuationTypeEnum $type,
		float $value,
	): StockValuationData
	{
		return new StockValuationData(
			$stockAsset,
			$type,
			$type->getTypeGroup(),
			$type->getTypeValueType(),
			new ImmutableDateTime('2026-01-01'),
			null,
			$value,
			CurrencyEnum::USD,
			new ImmutableDateTime('2026-01-01'),
		);
	}

	private function createDividend(StockAsset $stockAsset, string $exDate, float $amount): StockAssetDividend
	{
		return new StockAssetDividend(
			$stockAsset,
			new ImmutableDateTime($exDate),
			new ImmutableDateTime($exDate),
			null,
			CurrencyEnum::USD,
			$amount,
			new ImmutableDateTime('2026-01-01'),
			StockAssetDividendTypeEnum::REGULAR,
		);
	}

	private function createStockAsset(): StockAsset
	{
		return new StockAsset(
			'Apple Inc.',
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			'AAPL',
			StockAssetExchange::NASDAQ,
			CurrencyEnum::USD,
			new ImmutableDateTime('2026-01-01'),
			isin: null,
			stockAssetDividendSource: null,
			dividendTax: null,
			brokerDividendCurrency: null,
			shouldDownloadPrice: true,
			shouldDownloadValuation: true,
			watchlist: false,
			industry: null,
		);
	}

}
