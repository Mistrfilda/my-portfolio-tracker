<?php

declare(strict_types = 1);

namespace App\Test\Unit\Mcp\Tool;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Asset\Price\AssetPrice;
use App\Asset\Price\PriceDiff;
use App\Asset\Price\SummaryPrice;
use App\Currency\CurrencyEnum;
use App\Mcp\Support\StockAssetResolver;
use App\Mcp\Tool\StockValuationDetailTool;
use App\Stock\AiAnalysis\StockAiAnalysisCompanyDataProvider;
use App\Stock\Asset\Industry\StockAssetIndustry;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetDetailDTO;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Position\StockAssetPositionDetailDTO;
use App\Stock\Position\StockPositionFacade;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Price\StockAssetPriceRecord;
use App\Stock\Valuation\Comparison\Industry\StockIndustryComparisonFacade;
use App\Stock\Valuation\Data\StockValuationData;
use App\Stock\Valuation\MarginOfSafety\StockValuationMarginOfSafetyProvider;
use App\Stock\Valuation\Model\Consensus\StockValuationModelConsensusProvider;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationFacade;
use App\Stock\Valuation\StockValuationPriceProvider;
use App\Stock\Valuation\StockValuationTypeEnum;
use App\Stock\Valuation\StockValuationTypeGroupEnum;
use App\Stock\Valuation\StockValuationTypeValueTypeEnum;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;
use const DATE_ATOM;

class StockValuationDetailToolTest extends TestCase
{

	public function testReturnsCompleteValuationStripsScenariosWhenDisabledAndIncludesChanges(): void
	{
		$now = new ImmutableDateTime('2026-05-01 12:00:00');
		$prevTime = new ImmutableDateTime('2026-04-01 12:00:00');
		$owner = self::createStub(AppAdmin::class);
		$currentAdminGetter = self::createStub(CurrentAppAdminGetter::class);
		$currentAdminGetter->method('getAppAdmin')->willReturn($owner);

		$datetimeFactory = self::createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);

		$industry = new StockAssetIndustry(
			'Technology',
			'Technology',
			$now,
			25.0,
			null,
			null,
			null,
			6.0,
			5.0,
			1.5,
			22.0,
		);
		$asset = new StockAsset(
			'Microsoft',
			StockAssetPriceDownloaderEnum::WEB_SCRAP,
			'MSFT',
			StockAssetExchange::NASDAQ,
			CurrencyEnum::USD,
			$now,
			null,
			null,
			null,
			null,
			true,
			true,
			false,
			$industry,
		);
		$asset->setCurrentPrice(
			new StockAssetPriceRecord(
				$now,
				CurrencyEnum::USD,
				400.0,
				$asset,
				StockAssetPriceDownloaderEnum::WEB_SCRAP,
				$now,
			),
			$now,
		);

		$currentData = [
			StockValuationTypeEnum::TRAILING_PE->value => new StockValuationData(
				$asset,
				StockValuationTypeEnum::TRAILING_PE,
				StockValuationTypeGroupEnum::VALUATION,
				StockValuationTypeValueTypeEnum::FLOAT,
				$now,
				null,
				20.0,
				CurrencyEnum::USD,
				$now,
			),
			StockValuationTypeEnum::DILUTED_EPS->value => new StockValuationData(
				$asset,
				StockValuationTypeEnum::DILUTED_EPS,
				StockValuationTypeGroupEnum::FINANCIAL_HIGHLIGHTS,
				StockValuationTypeValueTypeEnum::FLOAT,
				$now,
				null,
				12.5,
				CurrencyEnum::USD,
				$now,
			),
		];
		$previousData = [
			StockValuationTypeEnum::DILUTED_EPS->value => new StockValuationData(
				$asset,
				StockValuationTypeEnum::DILUTED_EPS,
				StockValuationTypeGroupEnum::FINANCIAL_HIGHLIGHTS,
				StockValuationTypeValueTypeEnum::FLOAT,
				$prevTime,
				null,
				11.0,
				CurrencyEnum::USD,
				$prevTime,
			),
		];
		$valuation = new StockValuation($asset, $currentData, $previousData);

		$assetRepository = self::createStub(StockAssetRepository::class);
		$assetRepository->method('findByTickerCaseInsensitive')->willReturn($asset);

		$companyDataProvider = self::createStub(StockAiAnalysisCompanyDataProvider::class);
		$companyDataProvider->method('getData')->willReturn([
			'stockAssetTicker' => 'MSFT',
			'internalValuation' => [
				'models' => [
					[
						'model' => 'pe',
						'fairValue' => 450.0,
						'scenarios' => [['label' => 'Base', 'fairValue' => 450.0]],
					],
				],
			],
		]);

		$valuationFacade = self::createStub(StockValuationFacade::class);
		$valuationFacade->method('getStockValuation')->willReturn($valuation);
		$valuationFacade->method('getModelsForValuation')->willReturn([]);

		$priceProvider = self::createStub(StockValuationPriceProvider::class);
		$priceProvider->method('getAnalyticsPrice')->willReturn(new AssetPrice($asset, 480.0, CurrencyEnum::USD));
		$priceProvider->method('getAiAnalysisPrice')->willReturn(new AssetPrice($asset, 470.0, CurrencyEnum::USD));

		$positionDetail = new StockAssetDetailDTO(
			$asset,
			[self::createStub(StockAssetPositionDetailDTO::class)],
			new SummaryPrice(CurrencyEnum::USD, 3500.0),
			new SummaryPrice(CurrencyEnum::USD, 4000.0),
			new SummaryPrice(CurrencyEnum::USD, 4000.0),
			new SummaryPrice(CurrencyEnum::USD, 3500.0),
			new PriceDiff(500.0, 14.29, CurrencyEnum::USD),
			new PriceDiff(500.0, 14.29, CurrencyEnum::USD),
			new SummaryPrice(CurrencyEnum::CZK, 92000.0),
			new PriceDiff(11500.0, 14.29, CurrencyEnum::CZK),
			10,
		);
		$positionFacade = self::createStub(StockPositionFacade::class);
		$positionFacade->method('getStockAssetDetailDTO')->willReturn($positionDetail);

		$tool = new StockValuationDetailTool(
			$currentAdminGetter,
			$datetimeFactory,
			new StockAssetResolver($assetRepository),
			$companyDataProvider,
			$valuationFacade,
			new StockValuationModelConsensusProvider(),
			$priceProvider,
			new StockValuationMarginOfSafetyProvider(),
			new StockIndustryComparisonFacade(),
			$positionFacade,
		);

		$result = $tool->execute(ticker: 'MSFT', includeModelScenarios: false);

		self::assertSame($now->format(DATE_ATOM), $result['importedAt']);
		self::assertSame($prevTime->format(DATE_ATOM), $result['previousImportedAt']);
		self::assertSame([], $result['warnings']);
		self::assertIsArray($result['companyData']);
		self::assertIsArray($result['companyData']['internalValuation']);
		self::assertIsArray($result['companyData']['internalValuation']['models']);
		self::assertIsArray($result['companyData']['internalValuation']['models'][0]);
		self::assertArrayNotHasKey('scenarios', $result['companyData']['internalValuation']['models'][0]);

		self::assertIsArray($result['fundamentalChanges']);
		self::assertCount(1, $result['fundamentalChanges']);
		self::assertIsArray($result['fundamentalChanges'][0]);
		self::assertSame('diluted_eps', $result['fundamentalChanges'][0]['metric']);
		self::assertSame(11.0, $result['fundamentalChanges'][0]['previousValue']);
		self::assertSame(12.5, $result['fundamentalChanges'][0]['currentValue']);

		self::assertIsArray($result['industryComparison']);
		self::assertTrue($result['industryComparison']['hasIndustryData']);
		self::assertIsArray($result['positionSummary']);
		self::assertSame(10, $result['positionSummary']['pieces']);
		self::assertSame(350.0, $result['positionSummary']['averagePurchasePrice']);
	}

	public function testHandlesMissingValuationAndIndustryWithWarnings(): void
	{
		$now = new ImmutableDateTime('2026-05-01 12:00:00');
		$owner = self::createStub(AppAdmin::class);
		$currentAdminGetter = self::createStub(CurrentAppAdminGetter::class);
		$currentAdminGetter->method('getAppAdmin')->willReturn($owner);

		$datetimeFactory = self::createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);

		$asset = new StockAsset(
			'NewCo',
			StockAssetPriceDownloaderEnum::WEB_SCRAP,
			'NEW',
			StockAssetExchange::NASDAQ,
			CurrencyEnum::USD,
			$now,
			null,
			null,
			null,
			null,
			true,
			false,
			false,
			null,
		);

		$assetRepository = self::createStub(StockAssetRepository::class);
		$assetRepository->method('findByTickerCaseInsensitive')->willReturn($asset);

		$companyDataProvider = self::createStub(StockAiAnalysisCompanyDataProvider::class);
		$companyDataProvider->method('getData')->willReturn(['stockAssetTicker' => 'NEW']);

		$valuationFacade = self::createStub(StockValuationFacade::class);
		$valuationFacade->method('getStockValuation')->willReturn(new StockValuation($asset, []));
		$valuationFacade->method('getModelsForValuation')->willReturn([]);

		$priceProvider = self::createStub(StockValuationPriceProvider::class);
		$priceProvider->method('getAnalyticsPrice')->willReturn(null);
		$priceProvider->method('getAiAnalysisPrice')->willReturn(null);

		$emptyPositionDetail = new StockAssetDetailDTO(
			$asset,
			[],
			new SummaryPrice(CurrencyEnum::USD, 0.0),
			new SummaryPrice(CurrencyEnum::USD, 0.0),
			new SummaryPrice(CurrencyEnum::USD, 0.0),
			new SummaryPrice(CurrencyEnum::USD, 0.0),
			new PriceDiff(0.0, 0.0, CurrencyEnum::USD),
			new PriceDiff(0.0, 0.0, CurrencyEnum::USD),
			new SummaryPrice(CurrencyEnum::CZK, 0.0),
			new PriceDiff(0.0, 0.0, CurrencyEnum::CZK),
			0,
		);
		$positionFacade = self::createStub(StockPositionFacade::class);
		$positionFacade->method('getStockAssetDetailDTO')->willReturn($emptyPositionDetail);

		$tool = new StockValuationDetailTool(
			$currentAdminGetter,
			$datetimeFactory,
			new StockAssetResolver($assetRepository),
			$companyDataProvider,
			$valuationFacade,
			new StockValuationModelConsensusProvider(),
			$priceProvider,
			new StockValuationMarginOfSafetyProvider(),
			new StockIndustryComparisonFacade(),
			$positionFacade,
		);

		$result = $tool->execute(ticker: 'NEW');

		self::assertNull($result['importedAt']);
		self::assertNull($result['positionSummary']);
		self::assertIsArray($result['warnings']);
		self::assertCount(2, $result['warnings']);
	}

}
