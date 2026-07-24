<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation\Model\Consensus;

use App\Asset\Price\AssetPrice;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Model\Consensus\StockValuationModelConsensusConfidenceEnum;
use App\Stock\Valuation\Model\Consensus\StockValuationModelConsensusProvider;
use App\Stock\Valuation\Model\Price\BookValueValuationModel;
use App\Stock\Valuation\Model\Price\DebtAdjustedValuationModel;
use App\Stock\Valuation\Model\Price\FreeCashFlowValuationModel;
use App\Stock\Valuation\Model\Price\GrahamNumberValuationModel;
use App\Stock\Valuation\Model\Price\PriceEarningsValuationModel;
use App\Stock\Valuation\Model\Price\PriceToSalesValuationModel;
use App\Stock\Valuation\Model\Price\RoeQualityValuationModel;
use App\Stock\Valuation\Model\Price\StockValuationPriceModelResponse;
use App\Stock\Valuation\Model\StockValuationModel;
use App\Stock\Valuation\Model\StockValuationModelResponse;
use App\Stock\Valuation\Model\StockValuationModelState;
use PHPUnit\Framework\TestCase;

class StockValuationModelConsensusProviderTest extends TestCase
{

	public function testBalancesModelFamiliesAndKeepsRawDiagnostics(): void
	{
		$stockAsset = $this->createStockAsset(100.0);
		$provider = new StockValuationModelConsensusProvider();
		$responses = [
			$this->createResponse($stockAsset, new BookValueValuationModel(), 100.0, 'Book'),
			$this->createResponse($stockAsset, new DebtAdjustedValuationModel(), 110.0, 'Debt'),
			$this->createResponse($stockAsset, new RoeQualityValuationModel(), 120.0, 'ROE'),
			$this->createResponse($stockAsset, new GrahamNumberValuationModel(), 1000.0, 'Graham extreme'),
			$this->createResponse($stockAsset, new PriceEarningsValuationModel(), 200.0, 'P/E'),
			$this->createResponse($stockAsset, new PriceToSalesValuationModel(), 300.0, 'P/S'),
		];

		$consensus = $provider->getForStockAsset($stockAsset, $responses);

		self::assertSame(200.0, $consensus->getPrice()?->getPrice());
		self::assertSame(100.0, $consensus->getPercentage());
		self::assertSame(160.0, $consensus->getRawMedianPrice()?->getPrice());
		self::assertSame(305.0, $consensus->getArithmeticMeanPrice()?->getPrice());
		self::assertSame(115.0, $consensus->getLowerQuartilePrice()?->getPrice());
		self::assertSame(300.0, $consensus->getUpperQuartilePrice()?->getPrice());
		self::assertSame(92.5, $consensus->getNormalizedIqrPercentage());
		self::assertSame(47.5, $consensus->getNormalizedMadPercentage());
		self::assertSame(6, $consensus->getValidModelsCount());
		self::assertSame(6, $consensus->getTotalModelsCount());
		self::assertSame(3, $consensus->getValidFamiliesCount());
		self::assertSame(3, $consensus->getTotalFamiliesCount());
		self::assertSame(['Graham extreme'], $consensus->getOutlierModelLabels());
		self::assertSame(StockValuationModelConsensusConfidenceEnum::LOW, $consensus->getConfidence());
	}

	public function testReturnsHighConfidenceForFourAlignedFamilies(): void
	{
		$stockAsset = $this->createStockAsset(100.0);
		$provider = new StockValuationModelConsensusProvider();

		$consensus = $provider->getForStockAsset($stockAsset, [
			$this->createResponse($stockAsset, new BookValueValuationModel(), 100.0, 'Book'),
			$this->createResponse($stockAsset, new PriceEarningsValuationModel(), 105.0, 'P/E'),
			$this->createResponse($stockAsset, new FreeCashFlowValuationModel(), 110.0, 'FCF'),
			$this->createResponse($stockAsset, new PriceToSalesValuationModel(), 115.0, 'P/S'),
		]);

		self::assertSame(107.5, $consensus->getPrice()?->getPrice());
		self::assertSame(4, $consensus->getValidFamiliesCount());
		self::assertEqualsWithDelta(9.30, $consensus->getNormalizedIqrPercentage() ?? 0.0, 0.01);
		self::assertSame(StockValuationModelConsensusConfidenceEnum::HIGH, $consensus->getConfidence());
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
				new DebtAdjustedValuationModel(),
				120.0,
				'Debt CZK',
				CurrencyEnum::CZK,
			),
			$this->createResponse($stockAsset, new RoeQualityValuationModel(), null, 'ROE'),
			$this->createResponse($stockAsset, new GrahamNumberValuationModel(), -1.0, 'Graham'),
		]);

		self::assertSame(100.0, $consensus->getPrice()?->getPrice());
		self::assertSame(1, $consensus->getValidModelsCount());
		self::assertSame(4, $consensus->getTotalModelsCount());
		self::assertSame(1, $consensus->getValidFamiliesCount());
		self::assertSame(1, $consensus->getTotalFamiliesCount());
		self::assertSame(StockValuationModelConsensusConfidenceEnum::LOW, $consensus->getConfidence());
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
	): StockValuationModelResponse
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
