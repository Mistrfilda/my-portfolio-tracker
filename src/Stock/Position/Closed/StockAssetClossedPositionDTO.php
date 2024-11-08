<?php

declare(strict_types = 1);

namespace App\Stock\Position\Closed;

use App\Asset\Price\PriceDiff;
use App\Asset\Price\SummaryPrice;
use App\Stock\Asset\StockAsset;
use App\Stock\Position\StockPosition;

class StockAssetClossedPositionDTO
{

	/**
	 * @param array<StockPosition> $positions
	 */
	public function __construct(
		private StockAsset $stockAsset,
		private array $positions,
		private SummaryPrice $totalInvestedAmount,
		private SummaryPrice $totalAmount,
		private SummaryPrice|null $dividendsSummary,
		private SummaryPrice|null $totalAmountWithDividends,
		private SummaryPrice $totalInvestedAmountInBrokerCurrency,
		private SummaryPrice $totalAmountInBrokerCurrency,
		private SummaryPrice|null $totalAmountInBrokerCurrencyWithDividends,
		private SummaryPrice $totalInvestedAmountInBrokerCurrencyInCzk,
		private SummaryPrice $totalAmountInBrokerCurrencyInCzk,
		private SummaryPrice|null $totalAmountInBrokerCurrencyWithDividendsInCzk,
		private PriceDiff $totalAmountPriceDiff,
		private PriceDiff|null $totalAmountPriceDiffWithDividends,
		private PriceDiff $totalAmountPriceDiffInBrokerCurrency,
		private PriceDiff|null $totalAmountPriceDiffInBrokerCurrencyWithDividends,
		private PriceDiff $totalAmountPriceDiffInCzk,
		private PriceDiff|null $totalAmountPriceDiffInCzkWithDividends,
	)
	{
	}

	public function getStockAsset(): StockAsset
	{
		return $this->stockAsset;
	}

	/**
	 * @return array<StockPosition>
	 */
	public function getPositions(): array
	{
		return $this->positions;
	}

	public function getTotalInvestedAmount(): SummaryPrice
	{
		return $this->totalInvestedAmount;
	}

	public function getTotalAmount(): SummaryPrice
	{
		return $this->totalAmount;
	}

	public function getTotalInvestedAmountInBrokerCurrency(): SummaryPrice
	{
		return $this->totalInvestedAmountInBrokerCurrency;
	}

	public function getTotalAmountInBrokerCurrency(): SummaryPrice
	{
		return $this->totalAmountInBrokerCurrency;
	}

	public function getTotalInvestedAmountInBrokerCurrencyInCzk(): SummaryPrice
	{
		return $this->totalInvestedAmountInBrokerCurrencyInCzk;
	}

	public function getTotalAmountInBrokerCurrencyInCzk(): SummaryPrice
	{
		return $this->totalAmountInBrokerCurrencyInCzk;
	}

	public function getTotalAmountPriceDiff(): PriceDiff
	{
		return $this->totalAmountPriceDiff;
	}

	public function getTotalAmountPriceDiffInBrokerCurrency(): PriceDiff
	{
		return $this->totalAmountPriceDiffInBrokerCurrency;
	}

	public function getTotalAmountPriceDiffInCzk(): PriceDiff
	{
		return $this->totalAmountPriceDiffInCzk;
	}

	public function getTotalAmountWithDividends(): SummaryPrice|null
	{
		return $this->totalAmountWithDividends;
	}

	public function getTotalAmountInBrokerCurrencyWithDividends(): SummaryPrice|null
	{
		return $this->totalAmountInBrokerCurrencyWithDividends;
	}

	public function getTotalAmountInBrokerCurrencyWithDividendsInCzk(): SummaryPrice|null
	{
		return $this->totalAmountInBrokerCurrencyWithDividendsInCzk;
	}

	public function getTotalAmountPriceDiffWithDividends(): PriceDiff|null
	{
		return $this->totalAmountPriceDiffWithDividends;
	}

	public function getTotalAmountPriceDiffInBrokerCurrencyWithDividends(): PriceDiff|null
	{
		return $this->totalAmountPriceDiffInBrokerCurrencyWithDividends;
	}

	public function getTotalAmountPriceDiffInCzkWithDividends(): PriceDiff|null
	{
		return $this->totalAmountPriceDiffInCzkWithDividends;
	}

	public function getDividendsSummary(): SummaryPrice|null
	{
		return $this->dividendsSummary;
	}

}
