<?php

declare(strict_types = 1);

namespace App\Test\Unit\Asset;

use App\Asset\Asset;
use App\Asset\Price\AssetPrice;
use App\Asset\Price\AssetPriceFactory;
use App\Currency\CurrencyEnum;
use App\Test\UpdatedTestCase;
use Mockery;

class AssetPriceFactoryTest extends UpdatedTestCase
{

	public function testAssetPriceFactory(): void
	{
		$asset = Mockery::mock(Asset::class)->makePartial();
		$asset->expects('getCurrency')->andReturn(CurrencyEnum::USD);

		$expectedAssetPrice = new AssetPrice($asset, 30.9, CurrencyEnum::USD);

		self::assertEquals(
			$expectedAssetPrice,
			AssetPriceFactory::createFromPieceCountPrice(
				$asset,
				3,
				10.3,
			),
		);
	}

}
