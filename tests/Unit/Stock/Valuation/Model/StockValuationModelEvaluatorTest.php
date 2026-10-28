<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation\Model;

use App\Currency\CurrencyEnum;
use App\Stock\Valuation\Model\Price;
use App\Stock\Valuation\Model\StockValuationModelAvailability as Availability;
use App\Stock\Valuation\Model\StockValuationModelEvaluator;
use App\Stock\Valuation\StockValuation;
use App\Test\Unit\Stock\Valuation\ValuationFixture;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;
use const NAN;

class StockValuationModelEvaluatorTest extends TestCase
{

	public function testNegativeEquityExplainsAltriaModelsWithoutCallingDataMissing(): void
	{
		$valuation = ValuationFixture::create(
			['book_value_per_share' => -1.6, 'diluted_eps' => 4.75],
			ValuationFixture::industry(),
		);
		$evaluator = new StockValuationModelEvaluator();
		$models = [
			new Price\BookValueValuationModel(),
			new Price\GrahamNumberValuationModel(),
			new Price\RoeQualityValuationModel(),
			new Price\DebtAdjustedValuationModel(),
		];
		foreach ($models as $model) {
			$response = $evaluator->evaluate($model, $valuation);
			self::assertSame(Availability::NOT_APPLICABLE, $response->getAvailability());
			self::assertStringContainsString('kapitál', $response->getAvailabilityReason() ?? '');
			self::assertNull($response->getAssetPrice());
		}
	}

	public function testBankUsesEquityModelsInsteadOfIndustrialEnterpriseModels(): void
	{
		$valuation = ValuationFixture::create(
			['book_value_per_share' => 63.18, 'diluted_eps' => 13.13, 'forward_annual_dividend_rate' => 11.5],
			ValuationFixture::industry('Banks - Regional'),
		);
		$evaluator = new StockValuationModelEvaluator();
		$models = [
			new Price\FreeCashFlowValuationModel(),
			new Price\EnterpriseValueValuationModel(),
			new Price\DebtAdjustedValuationModel(),
			new Price\PriceToSalesValuationModel(),
		];
		foreach ($models as $model) {
			self::assertSame(Availability::NOT_APPLICABLE, $evaluator->evaluate($model, $valuation)->getAvailability());
		}

		$models = [
			new Price\BookValueValuationModel(),
			new Price\PriceEarningsValuationModel(),
			new Price\DividendDiscountValuationModel(),
		];
		foreach ($models as $model) {
			self::assertSame(Availability::AVAILABLE, $evaluator->evaluate($model, $valuation)->getAvailability());
		}
	}

	public function testMissingCashIsNotAssumedZeroButExplicitZeroIsValid(): void
	{
		$inputs = ['ebitda' => 1000.0, 'total_debt' => 100.0, 'shares_outstanding' => 100.0];
		$evaluator = new StockValuationModelEvaluator();
		$missing = $evaluator->evaluate(new Price\EnterpriseValueValuationModel(), ValuationFixture::create($inputs));
		self::assertSame(Availability::MISSING_DATA, $missing->getAvailability());
		self::assertStringContainsString('Hotovost', $missing->getAvailabilityReason() ?? '');
		$zero = $evaluator->evaluate(
			new Price\EnterpriseValueValuationModel(),
			ValuationFixture::create($inputs + ['total_cash' => 0.0]),
		);
		self::assertSame(Availability::AVAILABLE, $zero->getAvailability());
		self::assertSame(99.0, $zero->getCalculatedValue());
	}

	public function testNoFallbackForMissingIndustryPe(): void
	{
		$response = (new StockValuationModelEvaluator())->evaluate(
			new Price\PriceEarningsValuationModel(),
			ValuationFixture::create(['diluted_eps' => 5.0]),
		);
		self::assertSame(Availability::MISSING_DATA, $response->getAvailability());
		self::assertNull($response->getAssetPrice());
	}

	public function testRejectsMixedCurrenciesAndImportDates(): void
	{
		$valuation = ValuationFixture::create(['levered_free_cash_flow' => 1000.0, 'shares_outstanding' => 100.0]);
		$asset = $valuation->getStockAsset();
		$evaluator = new StockValuationModelEvaluator();
		foreach ([
			ValuationFixture::data(
				$asset,
				['levered_free_cash_flow' => 1000.0],
				new ImmutableDateTime('2026-10-25 12:00:00'),
				CurrencyEnum::EUR,
			),
			ValuationFixture::data(
				$asset,
				['levered_free_cash_flow' => 1000.0],
				new ImmutableDateTime('2026-09-25 12:00:00'),
			),
		] as $replacement) {
			$data = array_replace($valuation->getCurrentStockValuationData(), $replacement);
			$response = $evaluator->evaluate(new Price\FreeCashFlowValuationModel(), new StockValuation($asset, $data));
			self::assertSame(Availability::INVALID_DATA, $response->getAvailability());
			self::assertNull($response->getAssetPrice());
		}
	}

	public function testInvalidNumbersAndNonPositiveCashFlowAreDistinct(): void
	{
		$evaluator = new StockValuationModelEvaluator();
		foreach ([[NAN, Availability::INVALID_DATA], [-1.0, Availability::NOT_APPLICABLE], [null, Availability::MISSING_DATA]] as [$fcf, $expected]) {
			$response = $evaluator->evaluate(
				new Price\FreeCashFlowValuationModel(),
				ValuationFixture::create(['levered_free_cash_flow' => $fcf, 'shares_outstanding' => 100.0]),
			);
			self::assertSame($expected, $response->getAvailability());
		}
	}

	public function testNonDividendStockIsInapplicable(): void
	{
		$response = (new StockValuationModelEvaluator())->evaluate(
			new Price\DividendDiscountValuationModel(),
			ValuationFixture::create([], paysDividends: false),
		);
		self::assertSame(Availability::NOT_APPLICABLE, $response->getAvailability());
	}

}
