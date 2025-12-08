<?php

declare(strict_types = 1);

namespace App\Crypto\Position\Closed;

use App\Asset\Price\PriceDiff;
use App\Asset\Price\SummaryPrice;
use App\Crypto\Asset\CryptoAsset;
use App\Crypto\Position\CryptoPosition;

class CryptoAssetClossedPositionDTO
{

	/**
	 * @param array<CryptoPosition> $positions
	 */
	public function __construct(
		private CryptoAsset $cryptoAsset,
		private array $positions,
		private SummaryPrice $totalInvestedAmount,
		private SummaryPrice $totalAmount,
		private SummaryPrice $totalInvestedAmountInBrokerCurrency,
		private SummaryPrice $totalAmountInBrokerCurrency,
		private SummaryPrice $totalInvestedAmountInBrokerCurrencyInCzk,
		private SummaryPrice $totalAmountInBrokerCurrencyInCzk,
		private PriceDiff $totalAmountPriceDiff,
		private PriceDiff $totalAmountPriceDiffInBrokerCurrency,
		private PriceDiff $totalAmountPriceDiffInCzk,
	)
	{
	}

	public function getCryptoAsset(): CryptoAsset
	{
		return $this->cryptoAsset;
	}

	/**
	 * @return array<CryptoPosition>
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

}
