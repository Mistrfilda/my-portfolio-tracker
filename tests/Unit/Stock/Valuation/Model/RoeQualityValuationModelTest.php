<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation\Model;

use App\Stock\Valuation\Model\Price\RoeQualityValuationModel;
use App\Stock\Valuation\Model\StockValuationModelAvailability;
use App\Stock\Valuation\Model\StockValuationModelPurpose;
use App\Test\Unit\Stock\Valuation\ValuationFixture;
use PHPUnit\Framework\TestCase;

class RoeQualityValuationModelTest extends TestCase
{

	public function testReportsDiagnosticWithoutInventingPrice(): void
	{
		$response = (new RoeQualityValuationModel())->calculateResponse(
			ValuationFixture::create(['return_on_equity' => -5.0]),
		);
		self::assertSame(-5.0, $response->getCalculatedValue());
		self::assertNull($response->getAssetPrice());
		self::assertNull($response->getCalculatedPercentage());
		self::assertSame(StockValuationModelPurpose::DIAGNOSTIC, $response->getPurpose());
	}

	public function testMissingValueStaysMissing(): void
	{
		$response = (new RoeQualityValuationModel())->calculateResponse(
			ValuationFixture::create(['return_on_equity' => null]),
		);
		self::assertNull($response->getCalculatedValue());
		self::assertSame(StockValuationModelAvailability::MISSING_DATA, $response->getAvailability());
	}

	public function testDiagnosticDoesNotDependOnDailyPrice(): void
	{
		$model = new RoeQualityValuationModel();
		$low = $model->calculateResponse(ValuationFixture::create(['return_on_equity' => -5.0], price: 50.0));
		$high = $model->calculateResponse(ValuationFixture::create(['return_on_equity' => -5.0], price: 200.0));
		self::assertSame($low->getCalculatedValue(), $high->getCalculatedValue());
		self::assertNull($high->getAssetPrice());
	}

}
