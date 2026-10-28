<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation\Model;

use App\Stock\Valuation\Model\Price\PriceToSalesValuationModel;
use App\Test\Unit\Stock\Valuation\ValuationFixture;
use PHPUnit\Framework\TestCase;

class PriceToSalesValuationModelTest extends TestCase
{

	public function testUsesActualIndustryMultiple(): void
	{
		$model = new PriceToSalesValuationModel();
		$response = $model->calculateResponse(
			ValuationFixture::create(['revenue_per_share' => 80.0], ValuationFixture::industry(ps: 3.2)),
		);
		self::assertEqualsWithDelta(256.0, $response->getAssetPrice()?->getPrice() ?? 0.0, 0.001);
	}

	public function testNoUniversalFallbackWhenIndustryMissing(): void
	{
		$response = (new PriceToSalesValuationModel())->calculateResponse(
			ValuationFixture::create(['revenue_per_share' => 80.0]),
		);
		self::assertNull($response->getAssetPrice());
	}

	public function testRejectsNonPositiveInputAndMultiple(): void
	{
		$model = new PriceToSalesValuationModel();
		self::assertNull(
			$model->calculateResponse(
				ValuationFixture::create(['revenue_per_share' => -1.0], ValuationFixture::industry()),
			)->getAssetPrice(),
		);
		self::assertNull(
			$model->calculateResponse(
				ValuationFixture::create(['revenue_per_share' => 80.0], ValuationFixture::industry(ps: -2.0)),
			)->getAssetPrice(),
		);
	}

}
