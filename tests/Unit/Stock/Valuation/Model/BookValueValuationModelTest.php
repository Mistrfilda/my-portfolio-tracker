<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation\Model;

use App\Stock\Valuation\Model\Price\BookValueValuationModel;
use App\Test\Unit\Stock\Valuation\ValuationFixture;
use PHPUnit\Framework\TestCase;

class BookValueValuationModelTest extends TestCase
{

	public function testUsesActualIndustryMultiple(): void
	{
		$model = new BookValueValuationModel();
		$response = $model->calculateResponse(
			ValuationFixture::create(['book_value_per_share' => 80.0], ValuationFixture::industry(pb: 2.4)),
		);
		self::assertEqualsWithDelta(192.0, $response->getAssetPrice()?->getPrice() ?? 0.0, 0.001);
	}

	public function testNoUniversalFallbackWhenIndustryMissing(): void
	{
		$response = (new BookValueValuationModel())->calculateResponse(
			ValuationFixture::create(['book_value_per_share' => 80.0]),
		);
		self::assertNull($response->getAssetPrice());
	}

	public function testRejectsNonPositiveInputAndMultiple(): void
	{
		$model = new BookValueValuationModel();
		self::assertNull(
			$model->calculateResponse(
				ValuationFixture::create(['book_value_per_share' => -1.0], ValuationFixture::industry()),
			)->getAssetPrice(),
		);
		self::assertNull(
			$model->calculateResponse(
				ValuationFixture::create(['book_value_per_share' => 80.0], ValuationFixture::industry(pb: -2.0)),
			)->getAssetPrice(),
		);
	}

}
