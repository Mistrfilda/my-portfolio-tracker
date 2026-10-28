<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation\Model;

use App\Stock\Valuation\Model\Price\DividendDiscountValuationModel;
use App\Stock\Valuation\Model\Price\FreeCashFlowValuationModel;
use App\Stock\Valuation\Model\StockValuationModelAvailability;
use App\Stock\Valuation\Model\StockValuationModelEvaluator;
use App\Test\Unit\Stock\Valuation\ValuationFixture;
use PHPUnit\Framework\TestCase;

class ValuationScenariosTest extends TestCase
{

	public function testDdmScenariosKeepForwardDividendAsNextPayment(): void
	{
		$response = (new DividendDiscountValuationModel())->calculateResponse(
			ValuationFixture::create(['forward_annual_dividend_rate' => 4.44]),
		);
		$scenarios = $response->getScenarios();
		self::assertCount(3, $scenarios);
		self::assertEqualsWithDelta(44.4, $scenarios[0]->price->getPrice(), 0.0001);
		self::assertEqualsWithDelta(63.428571, $scenarios[1]->price->getPrice(), 0.0001);
		self::assertEqualsWithDelta(111.0, $scenarios[2]->price->getPrice(), 0.0001);
	}

	public function testFcfeScenariosGrowTrailingCashFlowAndSupportCustomAssumptions(): void
	{
		$response = (new FreeCashFlowValuationModel(requiredReturn: 0.12, growthRate: 0.02))->calculateResponse(
			ValuationFixture::create(['levered_free_cash_flow' => 1000.0, 'shares_outstanding' => 100.0]),
		);
		self::assertEqualsWithDelta(102.0, $response->getCalculatedValue() ?? 0.0, 0.0001);
		self::assertCount(3, $response->getScenarios());
		self::assertEqualsWithDelta(10.1 / 0.13, $response->getScenarios()[0]->price->getPrice(), 0.0001);
	}

	public function testRejectsGrowthAtOrAboveRequiredReturn(): void
	{
		$valuation = ValuationFixture::create(['forward_annual_dividend_rate' => 4.44]);
		$response = (new StockValuationModelEvaluator())->evaluate(
			new DividendDiscountValuationModel(0.03, 0.03),
			$valuation,
		);
		self::assertSame(StockValuationModelAvailability::INVALID_DATA, $response->getAvailability());
		self::assertSame([], $response->getScenarios());
	}

}
