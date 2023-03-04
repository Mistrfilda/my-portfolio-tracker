<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Record;

use App\Asset\Price\SummaryPrice;
use App\Currency\CurrencyConversionFacade;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Position\StockPosition;
use Doctrine\Common\Collections\ArrayCollection;
use Mistrfilda\Datetime\DatetimeFactory;

class StockAssetDividendRecordService
{

	public function __construct(
		private DatetimeFactory $datetimeFactory,
		private CurrencyConversionFacade $currencyConversionFacade,
	)
	{

	}

	/**
	 * @param ArrayCollection<int, StockAssetDividend> $stockAssetDividends
	 * @param ArrayCollection<int, StockPosition> $stockAssetPositions
	 * @return ArrayCollection<int, StockAssetDividendRecord>
	 */
	public function processDividendRecords(
		ArrayCollection $stockAssetDividends,
		ArrayCollection $stockAssetPositions,
	): ArrayCollection
	{
		$dividendRecords = new ArrayCollection();
		foreach ($stockAssetDividends as $stockAssetDividend) {
			$dividendSummaryPrice = new SummaryPrice($stockAssetDividend->getCurrency());
			foreach ($stockAssetPositions as $position) {
				if ($position->getOrderDate()->getTimestamp() < $stockAssetDividend->getExDate()->getTimestamp()) {
					$dividendSummaryPrice->addFlat(
						$stockAssetDividend->getAmount() * $position->getOrderPiecesCount(),
						$position->getOrderPiecesCount(),
					);
				}
			}

			if ($dividendSummaryPrice->isFilled()) {
				$brokerCurrencySummaryPrice = null;
				if ($stockAssetDividend->getStockAsset()->getBrokerDividendCurrency() !== null) {
					$brokerCurrencySummaryPrice = $this->currencyConversionFacade->getConvertedSummaryPrice(
						$dividendSummaryPrice,
						$stockAssetDividend->getStockAsset()->getBrokerDividendCurrency(),
					);
				}

				$dividendRecords->add(
					new StockAssetDividendRecord(
						$stockAssetDividend,
						$dividendSummaryPrice->getCounter(),
						$dividendSummaryPrice->getPrice(),
						$stockAssetDividend->getCurrency(),
						$brokerCurrencySummaryPrice?->getPrice(),
						$stockAssetDividend->getStockAsset()->getBrokerDividendCurrency(),
						$this->datetimeFactory->createNow(),
					),
				);
			}
		}

		return $dividendRecords;
	}

}
