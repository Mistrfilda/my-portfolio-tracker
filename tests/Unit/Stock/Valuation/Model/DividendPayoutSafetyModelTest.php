<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation\Model;

use App\Stock\Valuation\Model\Price\DividendPayoutSafetyModel;
use App\Stock\Valuation\Model\StockValuationModelAvailability;
use App\Stock\Valuation\Model\StockValuationModelPurpose;
use App\Test\Unit\Stock\Valuation\ValuationFixture;
use PHPUnit\Framework\TestCase;

class DividendPayoutSafetyModelTest extends TestCase
{

	public function testReportsDiagnosticWithoutInventingPrice(): void
	{
		$response = (new DividendPayoutSafetyModel())->calculateResponse(
			ValuationFixture::create(['payout_ratio' => 115.0]),
		);
		self::assertSame(115.0, $response->getCalculatedValue());
		self::assertNull($response->getAssetPrice());
		self::assertNull($response->getCalculatedPercentage());
		self::assertSame(StockValuationModelPurpose::DIAGNOSTIC, $response->getPurpose());
	}

	public function testMissingValueStaysMissing(): void
	{
		$response = (new DividendPayoutSafetyModel())->calculateResponse(
			ValuationFixture::create(['payout_ratio' => null]),
		);
		self::assertNull($response->getCalculatedValue());
		self::assertSame(StockValuationModelAvailability::MISSING_DATA, $response->getAvailability());
	}

	public function testDiagnosticDoesNotDependOnDailyPrice(): void
	{
		$model = new DividendPayoutSafetyModel();
		$low = $model->calculateResponse(ValuationFixture::create(['payout_ratio' => 115.0], price: 50.0));
		$high = $model->calculateResponse(ValuationFixture::create(['payout_ratio' => 115.0], price: 200.0));
		self::assertSame($low->getCalculatedValue(), $high->getCalculatedValue());
		self::assertNull($high->getAssetPrice());
	}

}
