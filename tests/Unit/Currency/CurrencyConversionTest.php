<?php

declare(strict_types = 1);


namespace App\Test\Unit\Currency;


use App\Asset\Asset;
use App\Asset\Price\AssetPrice;
use App\Asset\Price\PriceDiff;
use App\Asset\Price\SummaryPrice;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Test\UpdatedTestCase;
use Mockery;


class CurrencyConversionTest extends UpdatedTestCase
{
	private CurrencyConversionFacade $currencyConversionFacade;

	/**
	 * @dataProvider provideAssetPriceData
	 */
	public function testGetConvertedAssetPrice(
		AssetPrice $assetPrice,
		AssetPrice $expectedResult
	): void
	{
		self::assertEquals(
			$expectedResult,
			$this->currencyConversionFacade->getConvertedAssetPrice(
				$assetPrice,
				$expectedResult->getCurrency()
			)
		);
	}

	/**
	 * @dataProvider provideSummaryPrice
	 */
	public function testGetConvertedSummaryPrice(
		SummaryPrice $summaryPrice,
		SummaryPrice $expectedResult
	): void
	{
		self::assertEquals(
			$expectedResult,
			$this->currencyConversionFacade->getConvertedSummaryPrice(
				$summaryPrice,
				$expectedResult->getCurrency()
			)
		);
	}

	/**
	 * @dataProvider providePriceDiff
	 */
	public function testGetConvertedPriceDiff(
		PriceDiff $priceDiff,
		PriceDiff $expectedResult
	): void
	{
		self::assertEquals(
			$expectedResult,
			$this->currencyConversionFacade->getConvertedPriceDiff(
				$priceDiff,
				$expectedResult->getCurrencyEnum()
			)
		);
	}

	/**
	 * @return array<string, array<AssetPrice>>
	 */
	private function provideAssetPriceData(): array
	{
		$asset = Mockery::mock(Asset::class)->makePartial();

		return [
			'usd_eur' => [
				new AssetPrice($asset, 200, CurrencyEnum::USD),
				new AssetPrice($asset, 200 * CurrencyConversionHelper::USD_EUR, CurrencyEnum::EUR)
			],
			'eur_usd' => [
				new AssetPrice($asset, 200, CurrencyEnum::EUR),
				new AssetPrice($asset, 200 * CurrencyConversionHelper::EUR_USD, CurrencyEnum::USD)
			],
			'czk_usd' => [
				new AssetPrice($asset, 200, CurrencyEnum::CZK),
				new AssetPrice($asset, 200 * CurrencyConversionHelper::CZK_USD, CurrencyEnum::USD)
			],
			'usd_czk' => [
				new AssetPrice($asset, 200, CurrencyEnum::USD),
				new AssetPrice($asset, 200 * CurrencyConversionHelper::USD_CZK, CurrencyEnum::CZK)
			],
			'czk_eur' => [
				new AssetPrice($asset, 200, CurrencyEnum::CZK),
				new AssetPrice($asset, 200 * CurrencyConversionHelper::CZK_EUR, CurrencyEnum::EUR)
			],
			'eur_czk' => [
				new AssetPrice($asset, 200, CurrencyEnum::EUR),
				new AssetPrice($asset, 200 * CurrencyConversionHelper::EUR_CZK, CurrencyEnum::CZK)
			]
		];
	}
	
	private function provideSummaryPrice(): array
	{
		return [
			'usd_eur' => [
				new SummaryPrice(CurrencyEnum::USD, 500),
				new SummaryPrice(CurrencyEnum::EUR,500 * CurrencyConversionHelper::USD_EUR)
			],
			'eur_usd' => [
				new SummaryPrice(CurrencyEnum::EUR, 500),
				new SummaryPrice(CurrencyEnum::USD,500 * CurrencyConversionHelper::EUR_USD)
			],
			'czk_usd' => [
				new SummaryPrice(CurrencyEnum::CZK, 500),
				new SummaryPrice(CurrencyEnum::USD,500 * CurrencyConversionHelper::CZK_USD)
			],
			'usd_czk' => [
				new SummaryPrice(CurrencyEnum::USD, 500),
				new SummaryPrice(CurrencyEnum::CZK,500 * CurrencyConversionHelper::USD_CZK)
			],
			'czk_eur' => [
				new SummaryPrice(CurrencyEnum::CZK, 500),
				new SummaryPrice(CurrencyEnum::EUR,500 * CurrencyConversionHelper::CZK_EUR)
			],
			'eur_czk' => [
				new SummaryPrice(CurrencyEnum::EUR, 500),
				new SummaryPrice(CurrencyEnum::CZK,500 * CurrencyConversionHelper::EUR_CZK)
			]
		];
	}

	private function providePriceDiff(): array
	{
		return [
			'usd_eur' => [
				new PriceDiff(1000, 50.5, CurrencyEnum::USD),
				new PriceDiff(1000 * CurrencyConversionHelper::USD_EUR, 50.5, CurrencyEnum::EUR)
			],
			'eur_usd' => [
				new PriceDiff(1000, 50.5, CurrencyEnum::EUR),
				new PriceDiff(1000 * CurrencyConversionHelper::EUR_USD, 50.5, CurrencyEnum::USD)
			],
			'czk_usd' => [
				new PriceDiff(1000, 50.5, CurrencyEnum::CZK),
				new PriceDiff(1000 * CurrencyConversionHelper::CZK_USD, 50.5, CurrencyEnum::USD)
			],
			'usd_czk' => [
				new PriceDiff(1000, 50.5, CurrencyEnum::USD),
				new PriceDiff(1000 * CurrencyConversionHelper::USD_CZK, 50.5, CurrencyEnum::CZK)
			],
			'czk_eur' => [
				new PriceDiff(1000, 50.5, CurrencyEnum::CZK),
				new PriceDiff(1000 * CurrencyConversionHelper::CZK_EUR, 50.5, CurrencyEnum::EUR)
			],
			'eur_czk' => [
				new PriceDiff(1000, 50.5, CurrencyEnum::EUR),
				new PriceDiff(1000 * CurrencyConversionHelper::EUR_CZK, 50.5, CurrencyEnum::CZK)
			]
		];
	}


	protected function setUp(): void
	{
		parent::setUp();
		$this->currencyConversionFacade = new CurrencyConversionFacade(
			CurrencyConversionHelper::getConversionMockRepository()
		);
	}
}
