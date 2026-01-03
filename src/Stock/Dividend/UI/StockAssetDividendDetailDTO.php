<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\UI;

use App\Asset\Price\SummaryPrice;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Dividend\Record\StockAssetDividendRecord;
use App\Stock\Dividend\StockAssetDividend;

class StockAssetDividendDetailDTO
{

	private SummaryPrice $dividendRecordSummaryPrice;

	private SummaryPrice $dividendRecordSummaryPriceInCzk;

	private SummaryPrice $dividendRecordSummaryPriceWithTax;

	private SummaryPrice $dividendRecordSummaryPriceWithTaxInCzk;

	private SummaryPrice $dividendsSummaryPrice;

	private SummaryPrice $dividendsSummaryPriceInCzk;

	private SummaryPrice $dividendsSummaryPriceWithTax;

	private SummaryPrice $dividendsSummaryPriceWithTaxInCzk;

	/**
	 * @param array<StockAssetDividendRecord> $dividendRecords
	 * @param array<StockAssetDividend> $dividends
	 */
	public function __construct(
		CurrencyConversionFacade $currencyConversionFacade,
		private StockAsset $stockAsset,
		private array $dividendRecords,
		private array $dividends,
	)
	{
		$this->dividendRecordSummaryPrice = new SummaryPrice($this->stockAsset->getCurrency());
		$this->dividendRecordSummaryPriceInCzk = new SummaryPrice(CurrencyEnum::CZK);
		$this->dividendRecordSummaryPriceWithTax = new SummaryPrice($this->stockAsset->getCurrency());
		$this->dividendRecordSummaryPriceWithTaxInCzk = new SummaryPrice(CurrencyEnum::CZK);

		$this->dividendsSummaryPrice = new SummaryPrice($this->stockAsset->getCurrency());
		$this->dividendsSummaryPriceInCzk = new SummaryPrice(CurrencyEnum::CZK);
		$this->dividendsSummaryPriceWithTax = new SummaryPrice($this->stockAsset->getCurrency());
		$this->dividendsSummaryPriceWithTaxInCzk = new SummaryPrice(CurrencyEnum::CZK);

		foreach ($this->dividendRecords as $dividendRecord) {
			$this->dividendRecordSummaryPrice->addSummaryPrice($dividendRecord->getSummaryPrice());
			$this->dividendRecordSummaryPriceInCzk->addSummaryPrice(
				$currencyConversionFacade->getConvertedSummaryPrice(
					$dividendRecord->getSummaryPrice(),
					CurrencyEnum::CZK,
					$dividendRecord->getStockAssetDividend()->getExDate(),
				),
			);
			$this->dividendRecordSummaryPriceWithTax->addSummaryPrice($dividendRecord->getSummaryPrice(false));
			$this->dividendRecordSummaryPriceWithTaxInCzk->addSummaryPrice(
				$currencyConversionFacade->getConvertedSummaryPrice(
					$dividendRecord->getSummaryPrice(false),
					CurrencyEnum::CZK,
					$dividendRecord->getStockAssetDividend()->getExDate(),
				),
			);
		}

		foreach ($this->dividends as $dividend) {
			$this->dividendsSummaryPrice->addSummaryPrice($dividend->getSummaryPrice());
			$this->dividendsSummaryPriceInCzk->addSummaryPrice(
				$currencyConversionFacade->getConvertedSummaryPrice(
					$dividend->getSummaryPrice(),
					CurrencyEnum::CZK,
					$dividend->getExDate(),
				),
			);
			$this->dividendsSummaryPriceWithTax->addSummaryPrice($dividend->getSummaryPrice(false));
			$this->dividendsSummaryPriceWithTaxInCzk->addSummaryPrice(
				$currencyConversionFacade->getConvertedSummaryPrice(
					$dividend->getSummaryPrice(false),
					CurrencyEnum::CZK,
					$dividend->getExDate(),
				),
			);
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

	public function getDividendRecordSummaryPriceInCzk(): SummaryPrice
	{
		return $this->dividendRecordSummaryPriceInCzk;
	}

	public function getDividendRecordSummaryPriceWithTaxInCzk(): SummaryPrice
	{
		return $this->dividendRecordSummaryPriceWithTaxInCzk;
	}

	public function getDividendsSummaryPriceInCzk(): SummaryPrice
	{
		return $this->dividendsSummaryPriceInCzk;
	}

	public function getDividendsSummaryPriceWithTaxInCzk(): SummaryPrice
	{
		return $this->dividendsSummaryPriceWithTaxInCzk;
	}

}
