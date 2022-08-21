<?php

declare(strict_types = 1);

namespace App\Test\Unit\Asset;

use App\Asset\Asset;
use App\Asset\Price\AssetPrice;
use App\Asset\Price\Exception\AssetPriceInvalidAssetPricePassedException;
use App\Currency\CurrencyEnum;
use App\Test\UpdatedTestCase;
use Mockery;

class AssetPriceTest extends UpdatedTestCase
{

	public function testAddAssetPrice(): void
	{
		$assetPriceMock = Mockery::mock(Asset::class)->makePartial();

		$assetPrice = new AssetPrice($assetPriceMock, 300.3, CurrencyEnum::CZK);

		$assetPrice->addAssetPrice(
			new AssetPrice(
				$assetPriceMock,
				40.3,
				CurrencyEnum::CZK,
			),
		);

		self::assertEquals(
			new AssetPrice(
				$assetPriceMock,
				340.6,
				CurrencyEnum::CZK,
			),
			$assetPrice,
		);

		self::assertException(
			static function () use ($assetPriceMock, $assetPrice): void {
				$assetPrice->addAssetPrice(
					new AssetPrice(
						$assetPriceMock,
						340.6,
						CurrencyEnum::EUR,
					),
				);
			},
			AssetPriceInvalidAssetPricePassedException::class,
		);
	}

}
