<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis;

use App\Currency\CurrencyEnum;
use App\Stock\AiAnalysis\StockAiAnalysisCompanyDataProvider;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Price\StockAssetPriceRecordRepository;
use App\Stock\Valuation\Data\StockValuationData;
use App\Stock\Valuation\Data\StockValuationDataRepository;
use App\Stock\Valuation\Model\Consensus\StockValuationModelConsensusProvider;
use App\Stock\Valuation\Model\Price;
use App\Stock\Valuation\Model\StockValuationModelEvaluator;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationFacade;
use App\Stock\Valuation\StockValuationTypeEnum as Type;
use App\Test\Unit\Stock\Valuation\ValuationFixture;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;
use const DATE_ATOM;
use const NAN;

class StockAiAnalysisCompanyDataProviderTest extends TestCase
{

	public function testExportsFundamentalsAssumptionsAndOnlyPrimaryModelsInConsensus(): void
	{
		$valuation = ValuationFixture::create([
			'diluted_eps' => 5.0,
			'book_value_per_share' => 50.0,
			'levered_free_cash_flow' => 1000.0,
			'shares_outstanding' => 100.0,
			'ebitda' => 1000.0,
			'total_debt' => 100.0,
			'total_cash' => 0.0,
			'revenue_per_share' => 30.0,
			'forward_annual_dividend_rate' => 5.0,
			'return_on_equity' => 20.0,
			'peg_ratio' => 1.2,
			'payout_ratio' => 40.0,
			'total_debt_equity' => 50.0,
		], ValuationFixture::industry());
		$periodType = Type::MOST_RECENT_QUARTER;
		$importedAt = new ImmutableDateTime('2026-10-25 12:00:00');
		$period = new StockValuationData(
			$valuation->getStockAsset(),
			$periodType,
			$periodType->getTypeGroup(),
			$periodType->getTypeValueType(),
			$importedAt,
			'2026-09-30',
			null,
			CurrencyEnum::USD,
			$importedAt,
		);
		$valuation = new StockValuation(
			$valuation->getStockAsset(),
			$valuation->getCurrentStockValuationData() + [$periodType->value => $period],
		);
		$data = $this->createProvider($valuation)->getData($valuation->getStockAsset());

		self::assertSame(5.0, $data['dilutedEps']);
		self::assertSame(1000.0, $data['leveredFreeCashFlow']);
		self::assertSame(0.0, $data['totalCash']);
		self::assertNull($data['marketCap']);
		self::assertSame('2026-09-30', $data['mostRecentQuarter']);
		self::assertSame($importedAt->format(DATE_ATOM), $data['fundamentalMetadata']['dilutedEps']['importedAt']);
		self::assertSame('USD', $data['fundamentalMetadata']['dilutedEps']['currency']);
		self::assertSame('diluted_eps', $data['fundamentalMetadata']['dilutedEps']['metric']);
		self::assertNull($data['fundamentalMetadata']['returnOnEquity']['currency']);
		self::assertSame('percentage', $data['fundamentalMetadata']['returnOnEquity']['valueType']);
		self::assertSame('Tobacco', $data['internalValuation']['industry']['mappingName']);
		$models = array_column($data['internalValuation']['models'], null, 'model');
		$fcfe = $models['FreeCashFlowValuationModel'];
		self::assertSame('available', $fcfe['availability']);
		self::assertEqualsWithDelta(147.142857, $fcfe['fairValue'], 0.000001);
		self::assertSame([10.0, 3.0], array_column($fcfe['assumptions'], 'value'));
		self::assertContains('levered_free_cash_flow', $fcfe['inputMetrics']);
		self::assertSame(10.0, $fcfe['scenarios'][1]['requiredReturnPercent']);
		self::assertSame(3.0, $fcfe['scenarios'][1]['growthPercent']);
		self::assertSame($fcfe['fairValue'], $fcfe['scenarios'][1]['fairValue']);
		self::assertSame('diagnostic', $models['RoeQualityValuationModel']['purpose']);
		self::assertSame(20.0, $models['RoeQualityValuationModel']['diagnosticValue']);
		self::assertSame('%', $models['RoeQualityValuationModel']['diagnosticUnit']);
		self::assertNull($models['RoeQualityValuationModel']['fairValue']);
		self::assertSame('reference', $models['GrahamNumberValuationModel']['purpose']);
		self::assertSame('reference', $models['PriceToSalesValuationModel']['purpose']);
		$consensus = $data['internalValuation']['consensus'];
		self::assertSame(5, $consensus['validModelsCount']);
		self::assertSame(4, $consensus['validFamiliesCount']);
		self::assertSame(100.0, $consensus['fairValue']);
		self::assertSame(0.0, $consensus['upsidePercent']);
		self::assertCount(4, $consensus['families']);
		self::assertNotSame('', Json::encode($data));
	}

	public function testExportsExistingIndustryExclusionsAndTheirReasons(): void
	{
		$valuation = ValuationFixture::create([
			'diluted_eps' => 5.0,
			'book_value_per_share' => 50.0,
			'forward_annual_dividend_rate' => 5.0,
		], ValuationFixture::industry('Banks - Regional'));
		$data = $this->createProvider($valuation)->getData($valuation->getStockAsset());
		$models = array_column($data['internalValuation']['models'], null, 'model');
		foreach ([
			'FreeCashFlowValuationModel',
			'EnterpriseValueValuationModel',
			'DebtAdjustedValuationModel',
			'PriceToSalesValuationModel',
		] as $model) {
			self::assertSame('not_applicable', $models[$model]['availability']);
			self::assertStringContainsString('bank', $models[$model]['availabilityReason']);
			self::assertNull($models[$model]['fairValue']);
			self::assertSame([], $models[$model]['scenarios']);
		}

		self::assertSame('available', $models['PriceEarningsValuationModel']['availability']);
		self::assertSame(3, $data['internalValuation']['consensus']['validModelsCount']);
		self::assertSame(3, $data['internalValuation']['consensus']['totalModelsCount']);
	}

	public function testMissingInvalidAndInapplicableDataRemainDistinctAndJsonSafe(): void
	{
		$valuation = ValuationFixture::create([
			'levered_free_cash_flow' => NAN,
			'shares_outstanding' => 100.0,
		], paysDividends: false);
		$data = $this->createProvider($valuation)->getData($valuation->getStockAsset());
		$models = array_column($data['internalValuation']['models'], null, 'model');
		self::assertSame('invalid_data', $models['FreeCashFlowValuationModel']['availability']);
		self::assertSame('missing_data', $models['PriceEarningsValuationModel']['availability']);
		self::assertSame('not_applicable', $models['DividendDiscountValuationModel']['availability']);
		self::assertNull($data['leveredFreeCashFlow']);
		self::assertNull($data['internalValuation']['consensus']['fairValue']);
		self::assertSame('unknown', $data['internalValuation']['consensus']['confidence']);
		self::assertNotSame('', Json::encode($data));
	}

	public function testKeepsInputCurrencyAndImportDateWhenModelRejectsThem(): void
	{
		$valuation = ValuationFixture::create(['shares_outstanding' => 100.0]);
		$asset = $valuation->getStockAsset();
		$oldImport = new ImmutableDateTime('2026-09-01 12:00:00');
		$valuation = new StockValuation($asset, $valuation->getCurrentStockValuationData() + ValuationFixture::data(
			$asset,
			['levered_free_cash_flow' => 1000.0],
			$oldImport,
			CurrencyEnum::EUR,
		));
		$data = $this->createProvider($valuation)->getData($asset);
		self::assertSame('USD', $data['currency']);
		self::assertSame('EUR', $data['fundamentalMetadata']['leveredFreeCashFlow']['currency']);
		self::assertSame(
			$oldImport->format(DATE_ATOM),
			$data['fundamentalMetadata']['leveredFreeCashFlow']['importedAt'],
		);
		$models = array_column($data['internalValuation']['models'], null, 'model');
		self::assertSame('invalid_data', $models['FreeCashFlowValuationModel']['availability']);
		self::assertNotNull($models['FreeCashFlowValuationModel']['availabilityReason']);
		self::assertNull($models['FreeCashFlowValuationModel']['fairValue']);
	}

	private function createProvider(StockValuation $valuation): StockAiAnalysisCompanyDataProvider
	{
		$assets = $this->createStub(StockAssetRepository::class);
		$assets->method('getById')->willReturn($valuation->getStockAsset());
		$metrics = $this->createStub(StockValuationDataRepository::class);
		$metrics->method('findLatestForStockAsset')->willReturn($valuation->getCurrentStockValuationData());
		$metrics->method('findPreviousForStockAsset')->willReturn([]);
		$facade = new StockValuationFacade([
			new Price\BookValueValuationModel(),
			new Price\PriceEarningsValuationModel(),
			new Price\FreeCashFlowValuationModel(),
			new Price\EnterpriseValueValuationModel(),
			new Price\DividendDiscountValuationModel(),
			new Price\GrahamNumberValuationModel(),
			new Price\PriceToSalesValuationModel(),
			new Price\RoeQualityValuationModel(),
			new Price\DebtAdjustedValuationModel(),
			new Price\DividendPayoutSafetyModel(),
			new Price\DividendYieldFairValueModel(),
			new Price\PegRatioValuationModel(),
		], $assets, $metrics, new StockValuationModelEvaluator());
		$prices = $this->createStub(StockAssetPriceRecordRepository::class);
		$prices->method('findByStockAssetSinceDate')->willReturn([]);
		$clock = $this->createStub(DatetimeFactory::class);
		$clock->method('createNow')->willReturn(new ImmutableDateTime('2026-11-04 12:00:00'));

		return new StockAiAnalysisCompanyDataProvider(
			$facade,
			new StockValuationModelConsensusProvider(),
			$prices,
			$clock,
		);
	}

}
