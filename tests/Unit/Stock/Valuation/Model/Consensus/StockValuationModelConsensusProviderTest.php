<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation\Model\Consensus;

use App\Asset\Price\AssetPrice;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Model\Consensus\StockValuationModelConsensusConfidenceEnum;
use App\Stock\Valuation\Model\Consensus\StockValuationModelConsensusProvider;
use App\Stock\Valuation\Model\Price\BookValueValuationModel;
use App\Stock\Valuation\Model\Price\DividendDiscountValuationModel;
use App\Stock\Valuation\Model\Price\EnterpriseValueValuationModel;
use App\Stock\Valuation\Model\Price\FreeCashFlowValuationModel;
use App\Stock\Valuation\Model\Price\GrahamNumberValuationModel;
use App\Stock\Valuation\Model\Price\PriceEarningsValuationModel;
use App\Stock\Valuation\Model\Price\PriceToSalesValuationModel;
use App\Stock\Valuation\Model\Price\RoeQualityValuationModel;
use App\Stock\Valuation\Model\Price\StockValuationPriceModelResponse;
use App\Stock\Valuation\Model\StockValuationModel;
use App\Stock\Valuation\Model\StockValuationModelAvailability;
use App\Stock\Valuation\Model\StockValuationModelState;
use PHPUnit\Framework\TestCase;

class StockValuationModelConsensusProviderTest extends TestCase
{

	public function testBalancesModelFamiliesAndKeepsRawDiagnostics(): void
	{
		$stockAsset = $this->createStockAsset(100.0);
		$consensus = (new StockValuationModelConsensusProvider())->getForStockAsset($stockAsset, [
			$this->createResponse($stockAsset, new FreeCashFlowValuationModel(), 50.0, 'FCF'),
			$this->createResponse($stockAsset, new EnterpriseValueValuationModel(), 150.0, 'EV'),
			$this->createResponse($stockAsset, new BookValueValuationModel(), 200.0, 'Book'),
			$this->createResponse($stockAsset, new PriceEarningsValuationModel(), 300.0, 'PE'),
			$this->createResponse($stockAsset, new GrahamNumberValuationModel(), 1000.0, 'Reference'),
			$this->createResponse($stockAsset, new RoeQualityValuationModel(), 999.0, 'Diagnostic'),
		]);
		self::assertSame(200.0, $consensus->getPrice()?->getPrice());
		self::assertSame(175.0, $consensus->getRawMedianPrice()?->getPrice());
		self::assertSame(175.0, $consensus->getArithmeticMeanPrice()?->getPrice());
		self::assertSame(100.0, $consensus->getNormalizedIqrPercentage());
		self::assertSame(50.0, $consensus->getNormalizedMadPercentage());
		self::assertSame(4, $consensus->getValidModelsCount());
		self::assertSame(4, $consensus->getTotalModelsCount());
		self::assertSame(3, $consensus->getValidFamiliesCount());
		self::assertSame([], $consensus->getOutlierModelLabels());
	}

	public function testCoverageExcludesInapplicableModelsButCountsMissingInputs(): void
	{
		$stockAsset = $this->createStockAsset(100.0);
		$consensus = (new StockValuationModelConsensusProvider())->getForStockAsset($stockAsset, [
			$this->createResponse($stockAsset, new BookValueValuationModel(), null, 'Book')->withAvailability(
				StockValuationModelAvailability::NOT_APPLICABLE,
				'Negative equity',
			),
			$this->createResponse($stockAsset, new EnterpriseValueValuationModel(), null, 'EV')->withAvailability(
				StockValuationModelAvailability::MISSING_DATA,
				'Missing debt',
			),
			$this->createResponse($stockAsset, new PriceEarningsValuationModel(), 100.0, 'PE'),
			$this->createResponse($stockAsset, new PriceToSalesValuationModel(), 500.0, 'Reference'),
		]);
		self::assertSame(1, $consensus->getValidModelsCount());
		self::assertSame(2, $consensus->getTotalModelsCount());
		self::assertSame(2, $consensus->getTotalFamiliesCount());
		self::assertSame(StockValuationModelConsensusConfidenceEnum::UNKNOWN, $consensus->getConfidence());
	}

	public function testReturnsHighConfidenceForFourAlignedFamilies(): void
	{
		$stockAsset = $this->createStockAsset(100.0);
		$provider = new StockValuationModelConsensusProvider();

		$consensus = $provider->getForStockAsset($stockAsset, [
			$this->createResponse($stockAsset, new BookValueValuationModel(), 100.0, 'Book'),
			$this->createResponse($stockAsset, new PriceEarningsValuationModel(), 105.0, 'P/E'),
			$this->createResponse($stockAsset, new FreeCashFlowValuationModel(), 110.0, 'FCF'),
			$this->createResponse($stockAsset, new DividendDiscountValuationModel(), 115.0, 'P/S'),
		]);

		self::assertSame(107.5, $consensus->getPrice()?->getPrice());
		self::assertSame(4, $consensus->getValidFamiliesCount());
		self::assertEqualsWithDelta(9.30, $consensus->getNormalizedIqrPercentage() ?? 0.0, 0.01);
		self::assertSame(StockValuationModelConsensusConfidenceEnum::HIGH, $consensus->getConfidence());
	}

	public function testFamilyDispersionIgnoresSpreadWithinOneFamily(): void
	{
		$stockAsset = $this->createStockAsset(100.0);
		$consensus = (new StockValuationModelConsensusProvider())->getForStockAsset($stockAsset, [
			$this->createResponse($stockAsset, new FreeCashFlowValuationModel(), 50.0, 'Book'),
			$this->createResponse($stockAsset, new EnterpriseValueValuationModel(), 150.0, 'Debt'),
			$this->createResponse($stockAsset, new PriceEarningsValuationModel(), 100.0, 'P/E'),
		]);

		self::assertSame(100.0, $consensus->getPrice()?->getPrice());
		self::assertSame(0.0, $consensus->getNormalizedIqrPercentage());
		self::assertSame(0.0, $consensus->getNormalizedMadPercentage());
	}

	public function testReturnsMediumConfidenceForThreeAlignedFamilies(): void
	{
		$stockAsset = $this->createStockAsset(100.0);
		$provider = new StockValuationModelConsensusProvider();

		$consensus = $provider->getForStockAsset($stockAsset, [
			$this->createResponse($stockAsset, new BookValueValuationModel(), 100.0, 'Book'),
			$this->createResponse($stockAsset, new PriceEarningsValuationModel(), 110.0, 'P/E'),
			$this->createResponse($stockAsset, new FreeCashFlowValuationModel(), 120.0, 'FCF'),
		]);

		self::assertSame(110.0, $consensus->getPrice()?->getPrice());
		self::assertSame(StockValuationModelConsensusConfidenceEnum::MEDIUM, $consensus->getConfidence());
	}

	public function testFiltersUnavailableNonPositiveAndDifferentCurrencyPrices(): void
	{
		$stockAsset = $this->createStockAsset(100.0);
		$provider = new StockValuationModelConsensusProvider();

		$consensus = $provider->getForStockAsset($stockAsset, [
			$this->createResponse($stockAsset, new BookValueValuationModel(), 100.0, 'Book'),
			$this->createResponse(
				$stockAsset,
				new EnterpriseValueValuationModel(),
				120.0,
				'Debt CZK',
				CurrencyEnum::CZK,
			),
			$this->createResponse($stockAsset, new PriceEarningsValuationModel(), null, 'PE'),
			$this->createResponse($stockAsset, new DividendDiscountValuationModel(), -1.0, 'DDM'),
		]);

		self::assertSame(100.0, $consensus->getPrice()?->getPrice());
		self::assertSame(1, $consensus->getValidModelsCount());
		self::assertSame(4, $consensus->getTotalModelsCount());
		self::assertSame(1, $consensus->getValidFamiliesCount());
		self::assertSame(4, $consensus->getTotalFamiliesCount());
		self::assertSame(StockValuationModelConsensusConfidenceEnum::UNKNOWN, $consensus->getConfidence());
	}

	public function testReturnsUnknownConsensusWithoutModelResponses(): void
	{
		$stockAsset = $this->createStockAsset(100.0);
		$consensus = (new StockValuationModelConsensusProvider())->getForStockAsset($stockAsset, []);

		self::assertNull($consensus->getPrice());
		self::assertSame(0, $consensus->getValidModelsCount());
		self::assertSame(0, $consensus->getTotalModelsCount());
		self::assertSame(StockValuationModelConsensusConfidenceEnum::UNKNOWN, $consensus->getConfidence());
	}

	private function createStockAsset(float $currentPrice): StockAsset
	{
		$stockAsset = $this->createStub(StockAsset::class);
		$stockAsset->method('getAssetCurrentPrice')->willReturn(
			new AssetPrice($stockAsset, $currentPrice, CurrencyEnum::USD),
		);

		return $stockAsset;
	}

	private function createResponse(
		StockAsset $stockAsset,
		StockValuationModel $model,
		float|null $price,
		string $label,
		CurrencyEnum $currency = CurrencyEnum::USD,
	): StockValuationPriceModelResponse
	{
		return new StockValuationPriceModelResponse(
			stockValuationModel: $model,
			stockAsset: $stockAsset,
			assetPrice: $price === null ? null : new AssetPrice($stockAsset, $price, $currency),
			calculatedPercentage: $price === null ? null : $price - 100.0,
			calculatedValue: $price,
			usedStockValuationDataTypes: [],
			label: $label,
			state: $price === null
				? StockValuationModelState::UNABLE_TO_CALCULATE
				: StockValuationModelState::NEUTRAL,
		);
	}

}
