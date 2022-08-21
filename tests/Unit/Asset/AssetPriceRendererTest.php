<?php

declare(strict_types = 1);

namespace App\Test\Unit\Asset;

use App\Asset\Asset;
use App\Asset\Price\AssetPrice;
use App\Asset\Price\AssetPriceRenderer;
use App\Currency\CurrencyEnum;
use App\Test\UpdatedTestCase;
use Mockery;

class AssetPriceRendererTest extends UpdatedTestCase
{

	public function testAssetPriceRenderer(): void
	{
		$assetPriceRenderer = new AssetPriceRenderer();

		$assetPrice = new AssetPrice(
			Mockery::mock(Asset::class)->makePartial(),
			300.423,
			CurrencyEnum::CZK,
		);

		self::assertSame(
			'300.42 CZK',
			$assetPriceRenderer->getGridAssetPriceValue($assetPrice),
		);
	}

}
