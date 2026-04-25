<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Api;

use App\Asset\Price\AssetPrice;
use App\Asset\Price\SummaryPrice;
use App\Stock\Asset\StockAsset;
use App\Stock\Dividend\Record\StockAssetDividendRecord;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Position\StockPosition;
use App\Stock\Valuation\Model\StockValuationModelResponse;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\StockValuationFacade;
use App\Stock\Valuation\StockValuationPriceProvider;
use Mistrfilda\Datetime\DatetimeFactory;
use const DATE_ATOM;

class StockAssetSerializer
{

	public function __construct(
		private readonly DatetimeFactory $datetimeFactory,
		private readonly StockValuationFacade $stockValuationFacade,
		private readonly StockValuationPriceProvider $stockValuationPriceProvider,
	)
	{
	}

	/**
	 * @param array<StockAsset> $stockAssets
	 * @return array<mixed>
	 */
	public function serializeList(array $stockAssets): array
	{
		$data = [];
		foreach ($stockAssets as $stockAsset) {
			$data[] = $this->serialize($stockAsset);
		}

		return $data;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function serialize(StockAsset $stockAsset): array
	{
		return $this->serializeBase($stockAsset);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function serializeDetail(StockAsset $stockAsset): array
	{
		$data = $this->serializeBase($stockAsset);
		$data['openPositions'] = array_map(
			fn (StockPosition $stockPosition): array => $this->serializePosition($stockPosition),
			$stockAsset->getPositions(true),
		);
		$data['dividends'] = array_map(
			fn (StockAssetDividend $stockAssetDividend): array => $this->serializeDividend($stockAssetDividend),
			$stockAsset->getDividends(),
		);
		$data['valuationModels'] = array_map(
			fn (StockValuationModelResponse $stockValuationModelResponse): array => $this->serializeValuationModel(
				$stockValuationModelResponse,
			),
			$this->stockValuationFacade->getStockValuationsModelsForStockAsset($stockAsset),
		);

		return $data;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function serializeBase(StockAsset $stockAsset): array
	{
		$now = $this->datetimeFactory->createNow();
		$oneDayChange = $stockAsset->getTrend($now->deductDaysFromDatetime(1));
		$sevenDayChange = $stockAsset->getTrend($now->deductDaysFromDatetime(7));
		$thirtyDayChange = $stockAsset->getTrend($now->deductDaysFromDatetime(30));
		$priceFromAllModels = $this->stockValuationPriceProvider->getAverageModelPrice($stockAsset);
		$analyticsPrice = $this->stockValuationPriceProvider->getAnalyticsPrice($stockAsset);
		$aiAnalysisPrice = $this->stockValuationPriceProvider->getAiAnalysisPrice($stockAsset);

		return [
			'id' => $stockAsset->getId()->toString(),
			'name' => $stockAsset->getName(),
			'ticker' => $stockAsset->getTicker(),
			'isin' => $stockAsset->getIsin(),
			'exchange' => $stockAsset->getExchange()->value,
			'currency' => $stockAsset->getCurrency()->value,
			'price' => $stockAsset->getAssetCurrentPrice()->getPrice(),
			'trend' => $oneDayChange >= 0 ? 'increasing' : 'decreasing',
			'oneDayChange' => $oneDayChange,
			'sevenDayChange' => $sevenDayChange,
			'thirtyDayChange' => $thirtyDayChange,
			'priceFromAllModels' => $this->serializeAssetPrice($priceFromAllModels),
			'analyticsPrice' => $this->serializeAssetPrice($analyticsPrice),
			'aiAnalysisPrice' => $this->serializeAssetPrice($aiAnalysisPrice),
			'priceDownloadedAt' => $stockAsset->getPriceDownloadedAt()->format(DATE_ATOM),
			'assetPriceDownloader' => $stockAsset->getAssetPriceDownloader()->value,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function serializePosition(StockPosition $stockPosition): array
	{
		return [
			'id' => $stockPosition->getId()->toString(),
			'orderPiecesCount' => $stockPosition->getOrderPiecesCount(),
			'pricePerPiece' => $this->serializeAssetPrice($stockPosition->getPricePerPiece()),
			'totalInvestedAmount' => $this->serializeAssetPrice($stockPosition->getTotalInvestedAmount()),
			'currentTotalAmount' => $this->serializeAssetPrice($stockPosition->getCurrentTotalAmount()),
			'totalInvestedAmountInBrokerCurrency' => $this->serializeAssetPrice(
				$stockPosition->getTotalInvestedAmountInBrokerCurrency(),
			),
			'orderDate' => $stockPosition->getOrderDate()->format(DATE_ATOM),
			'differentBrokerAmount' => $stockPosition->isDifferentBrokerAmount(),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function serializeDividend(StockAssetDividend $stockAssetDividend): array
	{
		$records = $stockAssetDividend->getRecords();

		return [
			'id' => $stockAssetDividend->getId()->toString(),
			'exDate' => $stockAssetDividend->getExDate()->format(DATE_ATOM),
			'paymentDate' => $stockAssetDividend->getPaymentDate()?->format(DATE_ATOM),
			'declarationDate' => $stockAssetDividend->getDeclarationDate()?->format(DATE_ATOM),
			'amount' => $stockAssetDividend->getAmount(),
			'currency' => $stockAssetDividend->getCurrency()->value,
			'dividendType' => $stockAssetDividend->getDividendType()->value,
			'paidAmount' => $this->serializeSummaryPrice($this->sumPaidAmount($records)),
			'records' => array_map(
				fn (StockAssetDividendRecord $stockAssetDividendRecord): array => $this->serializeDividendRecord(
					$stockAssetDividendRecord,
				),
				$records,
			),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function serializeDividendRecord(StockAssetDividendRecord $stockAssetDividendRecord): array
	{
		return [
			'id' => $stockAssetDividendRecord->getId()->toString(),
			'totalPiecesHeldAtExDate' => $stockAssetDividendRecord->getTotalPiecesHeldAtExDate(),
			'totalAmount' => $stockAssetDividendRecord->getTotalAmount(),
			'currency' => $stockAssetDividendRecord->getCurrency()->value,
			'totalAmountInBrokerCurrency' => $stockAssetDividendRecord->getTotalAmountInBrokerCurrency(),
			'brokerCurrency' => $stockAssetDividendRecord->getBrokerCurrency()?->value,
			'reinvested' => $stockAssetDividendRecord->isReinvested(),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function serializeValuationModel(StockValuationModelResponse $stockValuationModelResponse): array
	{
		$modelTrend = $stockValuationModelResponse->getStockValuationModelTrend();

		return [
			'className' => $stockValuationModelResponse->getStockValuationModel()::class,
			'label' => $stockValuationModelResponse->getLabel(),
			'assetPrice' => $this->serializeAssetPrice($stockValuationModelResponse->getAssetPrice()),
			'calculatedPercentage' => $stockValuationModelResponse->getCalculatedPercentage(),
			'calculatedValue' => $stockValuationModelResponse->getCalculatedValue(),
			'state' => $modelTrend->value,
			'color' => $modelTrend->getTailwindColor(),
			'isCalculated' => $modelTrend !== StockValuationModelState::UNABLE_TO_CALCULATE,
			'description' => $stockValuationModelResponse->getDescription(),
		];
	}

	/**
	 * @param array<StockAssetDividendRecord> $records
	 */
	private function sumPaidAmount(array $records): SummaryPrice|null
	{
		$firstRecord = $records[0] ?? null;
		if ($firstRecord === null) {
			return null;
		}

		$totalAmount = 0.0;
		$currency = $firstRecord->getCurrency();
		foreach ($records as $record) {
			if ($currency !== $record->getCurrency()) {
				return null;
			}

			$totalAmount += $record->getTotalAmount();
		}

		return new SummaryPrice($currency, $totalAmount, 1);
	}

	/**
	 * @return array{amount: float, currency: string}|null
	 */
	private function serializeSummaryPrice(SummaryPrice|null $summaryPrice): array|null
	{
		if ($summaryPrice === null) {
			return null;
		}

		return [
			'amount' => $summaryPrice->getPrice(),
			'currency' => $summaryPrice->getCurrency()->value,
		];
	}

	/**
	 * @return array{price: float, currency: string}|null
	 */
	private function serializeAssetPrice(AssetPrice|null $assetPrice): array|null
	{
		if ($assetPrice === null) {
			return null;
		}

		return [
			'price' => $assetPrice->getPrice(),
			'currency' => $assetPrice->getCurrency()->value,
		];
	}

}
