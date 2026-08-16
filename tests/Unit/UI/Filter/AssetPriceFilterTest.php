<?php

declare(strict_types = 1);

namespace App\Test\Unit\UI\Filter;

use App\Asset\Price\AssetPriceEmbeddable;
use App\Currency\CurrencyEnum;
use App\UI\Filter\AssetPriceFilter;
use PHPUnit\Framework\TestCase;

class AssetPriceFilterTest extends TestCase
{

	public function testFormatsAssetPriceEmbeddable(): void
	{
		$price = new AssetPriceEmbeddable(40_000.0, CurrencyEnum::CZK);

		self::assertSame('40 000.00 CZK', AssetPriceFilter::format($price));
	}

}
