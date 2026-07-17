<?php

declare(strict_types = 1);

namespace App\Test\Unit\Currency;

use App\Asset\Asset;
use App\Asset\Price\AssetPrice;
use App\Asset\Price\PriceDiff;
use App\Asset\Price\SummaryPrice;
use App\Currency\CurrencyConversion;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyConversionRepository;
use App\Currency\CurrencyEnum;
use App\Currency\CurrencySourceEnum;
use App\Test\UpdatedTestCase;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;

class CurrencyConversionTest extends UpdatedTestCase
{

	private CurrencyConversionFacade $currencyConversionFacade;

	#[DataProvider('provideAssetPriceData')]
	public function testGetConvertedAssetPrice(
		AssetPrice $assetPrice,
		AssetPrice $expectedResult,
	): void
	{
		self::assertEquals(
			$expectedResult,
			$this->currencyConversionFacade->getConvertedAssetPrice(
				$assetPrice,
				$expectedResult->getCurrency(),
			),
		);
	}

	#[DataProvider('provideSummaryPrice')]
	public function testGetConvertedSummaryPrice(
		SummaryPrice $summaryPrice,
		SummaryPrice $expectedResult,
	): void
	{
		self::assertEquals(
			$expectedResult,
			$this->currencyConversionFacade->getConvertedSummaryPrice(
				$summaryPrice,
				$expectedResult->getCurrency(),
			),
		);
	}

	#[DataProvider('providePriceDiff')]
	public function testGetConvertedPriceDiff(
		PriceDiff $priceDiff,
		PriceDiff $expectedResult,
	): void
	{
		self::assertEquals(
			$expectedResult,
			$this->currencyConversionFacade->getConvertedPriceDiff(
				$priceDiff,
				$expectedResult->getCurrencyEnum(),
			),
		);
	}

	public function testCurrencyPairConversionIsLoadedOnlyOnce(): void
	{
		$currencyConversionRepository = Mockery::mock(CurrencyConversionRepository::class);
		$currencyConversionRepository->expects('getCurrentCurrencyPairConversion')
			->once()
			->with(CurrencyEnum::USD, CurrencyEnum::EUR)
			->andReturn($this->createCurrencyConversion(CurrencyConversionHelper::USD_EUR));

		$currencyConversionFacade = new CurrencyConversionFacade($currencyConversionRepository);
		$asset = Mockery::mock(Asset::class);

		$firstResult = $currencyConversionFacade->getConvertedAssetPrice(
			new AssetPrice($asset, 100, CurrencyEnum::USD),
			CurrencyEnum::EUR,
		);
		$secondResult = $currencyConversionFacade->getConvertedAssetPrice(
			new AssetPrice($asset, 200, CurrencyEnum::USD),
			CurrencyEnum::EUR,
		);

		self::assertSame(100 * CurrencyConversionHelper::USD_EUR, $firstResult->getPrice());
		self::assertSame(200 * CurrencyConversionHelper::USD_EUR, $secondResult->getPrice());
	}

	public function testHistoricalCurrencyConversionCacheIsSeparatedByDate(): void
	{
		$firstDate = new ImmutableDateTime('2026-01-01');
		$secondDate = new ImmutableDateTime('2026-01-02');
		$currencyConversionRepository = Mockery::mock(CurrencyConversionRepository::class);
		$currencyConversionRepository->expects('findCurrencyPairConversionForClosestDate')
			->once()
			->with(CurrencyEnum::USD, CurrencyEnum::EUR, $firstDate)
			->andReturn($this->createCurrencyConversion(0.9, $firstDate));
		$currencyConversionRepository->expects('findCurrencyPairConversionForClosestDate')
			->once()
			->with(CurrencyEnum::USD, CurrencyEnum::EUR, $secondDate)
			->andReturn($this->createCurrencyConversion(0.8, $secondDate));

		$currencyConversionFacade = new CurrencyConversionFacade($currencyConversionRepository);

		self::assertSame(
			90.0,
			$currencyConversionFacade->convertSimpleValue(
				100,
				CurrencyEnum::USD,
				CurrencyEnum::EUR,
				$firstDate,
			),
		);
		self::assertSame(
			180.0,
			$currencyConversionFacade->convertSimpleValue(
				200,
				CurrencyEnum::USD,
				CurrencyEnum::EUR,
				$firstDate,
			),
		);
		self::assertSame(
			80.0,
			$currencyConversionFacade->convertSimpleValue(
				100,
				CurrencyEnum::USD,
				CurrencyEnum::EUR,
				$secondDate,
			),
		);
	}

	/**
	 * @return array<string, array<AssetPrice>>
	 */
	public static function provideAssetPriceData(): array
	{
		$asset = Mockery::mock(Asset::class)->makePartial();

		return [
			'usd_eur' => [
				new AssetPrice($asset, 200, CurrencyEnum::USD),
				new AssetPrice($asset, 200 * CurrencyConversionHelper::USD_EUR, CurrencyEnum::EUR),
			],
			'eur_usd' => [
				new AssetPrice($asset, 200, CurrencyEnum::EUR),
				new AssetPrice($asset, 200 * CurrencyConversionHelper::EUR_USD, CurrencyEnum::USD),
			],
			'czk_usd' => [
				new AssetPrice($asset, 200, CurrencyEnum::CZK),
				new AssetPrice($asset, 200 * CurrencyConversionHelper::CZK_USD, CurrencyEnum::USD),
			],
			'usd_czk' => [
				new AssetPrice($asset, 200, CurrencyEnum::USD),
				new AssetPrice($asset, 200 * CurrencyConversionHelper::USD_CZK, CurrencyEnum::CZK),
			],
			'czk_eur' => [
				new AssetPrice($asset, 200, CurrencyEnum::CZK),
				new AssetPrice($asset, 200 * CurrencyConversionHelper::CZK_EUR, CurrencyEnum::EUR),
			],
			'eur_czk' => [
				new AssetPrice($asset, 200, CurrencyEnum::EUR),
				new AssetPrice($asset, 200 * CurrencyConversionHelper::EUR_CZK, CurrencyEnum::CZK),
			],
		];
	}

	/**
	 * @return array<string, array<SummaryPrice>>
	 */
	public static function provideSummaryPrice(): array
	{
		return [
			'usd_eur' => [
				new SummaryPrice(CurrencyEnum::USD, 500),
				new SummaryPrice(CurrencyEnum::EUR, 500 * CurrencyConversionHelper::USD_EUR),
			],
			'eur_usd' => [
				new SummaryPrice(CurrencyEnum::EUR, 500),
				new SummaryPrice(CurrencyEnum::USD, 500 * CurrencyConversionHelper::EUR_USD),
			],
			'czk_usd' => [
				new SummaryPrice(CurrencyEnum::CZK, 500),
				new SummaryPrice(CurrencyEnum::USD, 500 * CurrencyConversionHelper::CZK_USD),
			],
			'usd_czk' => [
				new SummaryPrice(CurrencyEnum::USD, 500),
				new SummaryPrice(CurrencyEnum::CZK, 500 * CurrencyConversionHelper::USD_CZK),
			],
			'czk_eur' => [
				new SummaryPrice(CurrencyEnum::CZK, 500),
				new SummaryPrice(CurrencyEnum::EUR, 500 * CurrencyConversionHelper::CZK_EUR),
			],
			'eur_czk' => [
				new SummaryPrice(CurrencyEnum::EUR, 500),
				new SummaryPrice(CurrencyEnum::CZK, 500 * CurrencyConversionHelper::EUR_CZK),
			],
		];
	}

	/**
	 * @return array<string, array<PriceDiff>>
	 */
	public static function providePriceDiff(): array
	{
		return [
			'usd_eur' => [
				new PriceDiff(1000, 50.5, CurrencyEnum::USD),
				new PriceDiff(1000 * CurrencyConversionHelper::USD_EUR, 50.5, CurrencyEnum::EUR),
			],
			'eur_usd' => [
				new PriceDiff(1000, 50.5, CurrencyEnum::EUR),
				new PriceDiff(1000 * CurrencyConversionHelper::EUR_USD, 50.5, CurrencyEnum::USD),
			],
			'czk_usd' => [
				new PriceDiff(1000, 50.5, CurrencyEnum::CZK),
				new PriceDiff(1000 * CurrencyConversionHelper::CZK_USD, 50.5, CurrencyEnum::USD),
			],
			'usd_czk' => [
				new PriceDiff(1000, 50.5, CurrencyEnum::USD),
				new PriceDiff(1000 * CurrencyConversionHelper::USD_CZK, 50.5, CurrencyEnum::CZK),
			],
			'czk_eur' => [
				new PriceDiff(1000, 50.5, CurrencyEnum::CZK),
				new PriceDiff(1000 * CurrencyConversionHelper::CZK_EUR, 50.5, CurrencyEnum::EUR),
			],
			'eur_czk' => [
				new PriceDiff(1000, 50.5, CurrencyEnum::EUR),
				new PriceDiff(1000 * CurrencyConversionHelper::EUR_CZK, 50.5, CurrencyEnum::CZK),
			],
		];
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->currencyConversionFacade = new CurrencyConversionFacade(
			CurrencyConversionHelper::getConversionMockRepository(),
		);
	}

	private function createCurrencyConversion(
		float $currentPrice,
		ImmutableDateTime|null $forDate = null,
	): CurrencyConversion
	{
		$now = new ImmutableDateTime();

		return new CurrencyConversion(
			CurrencyEnum::USD,
			CurrencyEnum::EUR,
			$currentPrice,
			CurrencySourceEnum::ECB,
			$now,
			$forDate ?? $now,
		);
	}

}
