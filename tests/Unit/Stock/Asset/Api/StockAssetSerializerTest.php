<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Asset\Api;

use App\Asset\Price\AssetPrice;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\Api\StockAssetSerializer;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Dividend\Record\StockAssetDividendRecord;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Dividend\StockAssetDividendTypeEnum;
use App\Stock\Position\StockPosition;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Price\StockAssetPriceRecord;
use App\Stock\Valuation\Model\StockValuationModel;
use App\Stock\Valuation\Model\StockValuationModelResponse;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\StockValuationFacade;
use App\Stock\Valuation\StockValuationPriceProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use ReflectionProperty;

#[AllowMockObjectsWithoutExpectations]
class StockAssetSerializerTest extends TestCase
{

	public function testSerializeUsesPreviousTradingDayOnSaturday(): void
	{
		$stockAsset = $this->createStockAssetWithCurrentFridayPrice();
		$serializer = $this->createSerializer(new ImmutableDateTime('2026-01-10 09:00:00'));

		$data = $serializer->serialize($stockAsset);

		self::assertSame(10.0, $data['oneDayChange']);
	}

	public function testSerializeUsesPreviousTradingDayOnMondayBeforeMarketOpen(): void
	{
		$stockAsset = $this->createStockAssetWithCurrentFridayPrice();
		$serializer = $this->createSerializer(new ImmutableDateTime('2026-01-12 08:00:00'));

		$data = $serializer->serialize($stockAsset);

		self::assertSame(10.0, $data['oneDayChange']);
	}

	public function testSerializeContainsValuationPrices(): void
	{
		$stockAsset = $this->createStockAssetWithCurrentFridayPrice();
		$serializer = $this->createSerializer(
			new ImmutableDateTime('2026-01-12 08:00:00'),
			10.5,
			11.5,
			12.5,
		);

		$data = $serializer->serialize($stockAsset);

		self::assertSame(['price' => 10.5, 'currency' => CurrencyEnum::USD->value], $data['priceFromAllModels']);
		self::assertSame(['price' => 11.5, 'currency' => CurrencyEnum::USD->value], $data['analyticsPrice']);
		self::assertSame(['price' => 12.5, 'currency' => CurrencyEnum::USD->value], $data['aiAnalysisPrice']);
	}

	public function testSerializeDetailContainsOpenPositionsDividendsAndValuationModels(): void
	{
		$stockAsset = $this->createStockAssetWithCurrentFridayPrice();
		$position = $this->createMock(StockPosition::class);
		$position->method('getId')->willReturn(Uuid::fromString('00000000-0000-0000-0000-000000000001'));
		$position->method('getOrderPiecesCount')->willReturn(4);
		$position->method('getPricePerPiece')->willReturn($this->createAssetPrice(10.0));
		$position->method('getTotalInvestedAmount')->willReturn($this->createAssetPrice(40.0));
		$position->method('getCurrentTotalAmount')->willReturn($this->createAssetPrice(44.0));
		$position->method('getTotalInvestedAmountInBrokerCurrency')->willReturn(
			$this->createAssetPrice(900.0, CurrencyEnum::CZK),
		);
		$position->method('getOrderDate')->willReturn(new ImmutableDateTime('2026-01-01 00:00:00'));
		$position->method('isDifferentBrokerAmount')->willReturn(true);

		$record = $this->createMock(StockAssetDividendRecord::class);
		$record->method('getId')->willReturn(Uuid::fromString('00000000-0000-0000-0000-000000000002'));
		$record->method('getTotalPiecesHeldAtExDate')->willReturn(4);
		$record->method('getTotalAmount')->willReturn(3.5);
		$record->method('getCurrency')->willReturn(CurrencyEnum::USD);
		$record->method('getTotalAmountInBrokerCurrency')->willReturn(80.0);
		$record->method('getBrokerCurrency')->willReturn(CurrencyEnum::CZK);
		$record->method('isReinvested')->willReturn(true);

		$dividend = $this->createMock(StockAssetDividend::class);
		$dividend->method('getId')->willReturn(Uuid::fromString('00000000-0000-0000-0000-000000000003'));
		$dividend->method('getExDate')->willReturn(new ImmutableDateTime('2026-02-01 00:00:00'));
		$dividend->method('getPaymentDate')->willReturn(new ImmutableDateTime('2026-02-15 00:00:00'));
		$dividend->method('getDeclarationDate')->willReturn(new ImmutableDateTime('2026-01-20 00:00:00'));
		$dividend->method('getAmount')->willReturn(0.875);
		$dividend->method('getCurrency')->willReturn(CurrencyEnum::USD);
		$dividend->method('getDividendType')->willReturn(StockAssetDividendTypeEnum::REGULAR);
		$dividend->method('getRecords')->willReturn([$record]);

		$model = $this->createMock(StockValuationModel::class);
		$modelResponse = $this->createMock(StockValuationModelResponse::class);
		$modelResponse->method('getStockValuationModel')->willReturn($model);
		$modelResponse->method('getLabel')->willReturn('Discounted cash flow');
		$modelResponse->method('getAssetPrice')->willReturn($this->createAssetPrice(123.45));
		$modelResponse->method('getCalculatedPercentage')->willReturn(12.3);
		$modelResponse->method('getCalculatedValue')->willReturn(15.6);
		$modelResponse->method('getStockValuationModelTrend')->willReturn(StockValuationModelState::UNDERPRICED);
		$modelResponse->method('getDescription')->willReturn('Model description');

		$positionsReflection = new ReflectionProperty($stockAsset, 'positions');
		$positionsReflection->setValue($stockAsset, new ArrayCollection([$position]));
		$dividendsReflection = new ReflectionProperty($stockAsset, 'dividends');
		$dividendsReflection->setValue($stockAsset, new ArrayCollection([$dividend]));

		$serializer = $this->createSerializer(
			new ImmutableDateTime('2026-01-12 08:00:00'),
			stockAsset: $stockAsset,
			valuationModels: [$modelResponse],
		);

		$data = $serializer->serializeDetail($stockAsset);

		self::assertSame('00000000-0000-0000-0000-000000000001', $data['openPositions'][0]['id']);
		self::assertSame(
			['amount' => 3.5, 'currency' => CurrencyEnum::USD->value],
			$data['dividends'][0]['paidAmount'],
		);
		self::assertSame('00000000-0000-0000-0000-000000000002', $data['dividends'][0]['records'][0]['id']);
		self::assertSame('Discounted cash flow', $data['valuationModels'][0]['label']);
	}

	/**
	 * @param array<StockValuationModelResponse> $valuationModels
	 */
	private function createSerializer(
		ImmutableDateTime $now,
		float|null $priceFromAllModels = null,
		float|null $analyticsPrice = null,
		float|null $aiAnalysisPrice = null,
		StockAsset|null $stockAsset = null,
		array $valuationModels = [],
	): StockAssetSerializer
	{
		$datetimeFactory = $this->createMock(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);
		$stockValuationFacade = $this->createMock(StockValuationFacade::class);
		if ($stockAsset !== null) {
			$stockValuationFacade->expects(self::once())->method('getStockValuationsModelsForStockAsset')->with(
				$stockAsset,
			)->willReturn($valuationModels);
		}

		$stockValuationPriceProvider = $this->createMock(StockValuationPriceProvider::class);
		$stockValuationPriceProvider->method('getAverageModelPrice')->willReturn(
			$this->createAssetPrice($priceFromAllModels),
		);
		$stockValuationPriceProvider->method('getAnalyticsPrice')->willReturn(
			$this->createAssetPrice($analyticsPrice),
		);
		$stockValuationPriceProvider->method('getAiAnalysisPrice')->willReturn(
			$this->createAssetPrice($aiAnalysisPrice),
		);

		return new StockAssetSerializer($datetimeFactory, $stockValuationFacade, $stockValuationPriceProvider);
	}

	private function createAssetPrice(float|null $price, CurrencyEnum $currency = CurrencyEnum::USD): AssetPrice|null
	{
		if ($price === null) {
			return null;
		}

		$stockAsset = $this->createMock(StockAsset::class);
		$stockAsset->method('getCurrency')->willReturn($currency);

		return new AssetPrice($stockAsset, $price, $currency);
	}

	private function createStockAssetWithCurrentFridayPrice(): StockAsset
	{
		$stockAsset = new StockAsset(
			'Apple Inc.',
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			'AAPL',
			StockAssetExchange::NASDAQ,
			CurrencyEnum::USD,
			new ImmutableDateTime('2026-01-09 22:00:00'),
			isin: null,
			stockAssetDividendSource: null,
			dividendTax: null,
			brokerDividendCurrency: null,
			shouldDownloadPrice: true,
			shouldDownloadValuation: false,
			watchlist: false,
			industry: null,
		);

		$thursdayPriceRecord = new StockAssetPriceRecord(
			new ImmutableDateTime('2026-01-08'),
			CurrencyEnum::USD,
			100.0,
			$stockAsset,
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			new ImmutableDateTime('2026-01-08 22:00:00'),
		);

		$fridayPriceRecord = new StockAssetPriceRecord(
			new ImmutableDateTime('2026-01-09'),
			CurrencyEnum::USD,
			110.0,
			$stockAsset,
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			new ImmutableDateTime('2026-01-09 22:00:00'),
		);

		$priceRecordsReflection = new ReflectionProperty($stockAsset, 'priceRecords');
		$priceRecordsReflection->setValue($stockAsset, new ArrayCollection([$thursdayPriceRecord, $fridayPriceRecord]));

		$stockAsset->setCurrentPrice($fridayPriceRecord, new ImmutableDateTime('2026-01-09 22:00:00'));

		return $stockAsset;
	}

}
