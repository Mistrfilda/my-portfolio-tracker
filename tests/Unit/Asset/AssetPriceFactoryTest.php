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

		$actual = AssetPriceFactory::createFromPieceCountPrice($asset, 3, 10.3);

		self::assertEquals(
			$expectedAssetPrice->getAsset(),
			$actual->getAsset(),
		);

		self::assertEquals(
			$expectedAssetPrice->getCurrency(),
			$actual->getCurrency(),
		);

		self::assertEqualsWithDelta(
			$expectedAssetPrice->getPrice(),
			$actual->getPrice(),
			0.00001,
		);
	}

}
