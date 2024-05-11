<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\UI;

use App\Asset\Price\SummaryPrice;
use App\Stock\Asset\StockAsset;
use App\Stock\Dividend\Record\StockAssetDividendRecord;
use App\Stock\Dividend\StockAssetDividend;

class StockAssetDividendDetailDTO
{

	private SummaryPrice $dividendRecordSummaryPrice;

	private SummaryPrice $dividendRecordSummaryPriceWithTax;

	private SummaryPrice $dividendsSummaryPrice;

	private SummaryPrice $dividendsSummaryPriceWithTax;

	/**
	 * @param array<StockAssetDividendRecord> $dividendRecords
	 * @param array<StockAssetDividend> $dividends
	 */
	public function __construct(
		private StockAsset $stockAsset,
		private array $dividendRecords,
		private array $dividends,
	)
	{
		$this->dividendRecordSummaryPrice = new SummaryPrice($this->stockAsset->getCurrency());
		$this->dividendRecordSummaryPriceWithTax = new SummaryPrice($this->stockAsset->getCurrency());

		$this->dividendsSummaryPrice = new SummaryPrice($this->stockAsset->getCurrency());
		$this->dividendsSummaryPriceWithTax = new SummaryPrice($this->stockAsset->getCurrency());

		foreach ($this->dividendRecords as $dividendRecord) {
			$this->dividendRecordSummaryPrice->addSummaryPrice($dividendRecord->getSummaryPrice());
			$this->dividendRecordSummaryPriceWithTax->addSummaryPrice($dividendRecord->getSummaryPrice(false));
		}

		foreach ($this->dividends as $dividend) {
			$this->dividendsSummaryPrice->addSummaryPrice($dividend->getSummaryPrice());
			$this->dividendsSummaryPriceWithTax->addSummaryPrice($dividend->getSummaryPrice(false));
		}
	}

	public function getDividendRecordSummaryPrice(): SummaryPrice
	{
		return $this->dividendRecordSummaryPrice;
	}

	public function getDividendRecordSummaryPriceWithTax(): SummaryPrice
	{
		return $this->dividendRecordSummaryPriceWithTax;
	}

	public function getDividendsSummaryPrice(): SummaryPrice
	{
		return $this->dividendsSummaryPrice;
	}

	public function getDividendsSummaryPriceWithTax(): SummaryPrice
	{
		return $this->dividendsSummaryPriceWithTax;
	}

}
