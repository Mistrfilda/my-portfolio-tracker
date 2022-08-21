<?php

declare(strict_types = 1);

namespace App\Test\Unit\Asset;

use App\Asset\Price\AssetPrice;
use App\Asset\Price\AssetPriceService;
use App\Asset\Price\Exception\PriceDiffException;
use App\Asset\Price\PriceDiff;
use App\Currency\CurrencyEnum;
use App\Test\UpdatedTestCase;
use Mockery;

class AssetPriceServiceTest extends UpdatedTestCase
{

	public function testAssetPriceServiceDiff(): void
	{
		$assetPriceService = new AssetPriceService();

		$asset1 = Mockery::mock(AssetPrice::class)->makePartial();
		$asset1->expects('getCurrency')->andReturn(CurrencyEnum::CZK);
		$asset1->expects('getPrice')->andReturn(100);

		$asset2 = Mockery::mock(AssetPrice::class)->makePartial();
		$asset2->expects('getCurrency')->andReturn(CurrencyEnum::CZK);
		$asset2->expects('getPrice')->andReturn(50);

		$diff = $assetPriceService->getAssetPriceDiff($asset1, $asset2);

		$expectedDiff = new PriceDiff(50, 100 * 100 / 50, CurrencyEnum::CZK);

		self::assertEquals($expectedDiff, $diff);
	}

	public function testDiferentCurrencyException(): void
	{
		$assetPriceService = new AssetPriceService();

		$asset1 = Mockery::mock(AssetPrice::class)->makePartial();
		$asset1->expects('getCurrency')->andReturn(CurrencyEnum::CZK);
		$asset1->expects('getPrice')->andReturn(100);

		$asset2 = Mockery::mock(AssetPrice::class)->makePartial();
		$asset2->expects('getCurrency')->andReturn(CurrencyEnum::EUR);
		$asset2->expects('getPrice')->andReturn(50);

		self::assertException(
			static function () use ($assetPriceService, $asset1, $asset2): void {
				$assetPriceService->getAssetPriceDiff(
					$asset1,
					$asset2,
				);
			},
			PriceDiffException::class,
			'Currency must be same',
		);
	}

}
