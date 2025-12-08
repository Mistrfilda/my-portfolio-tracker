<?php

declare(strict_types = 1);

namespace App\Crypto\Asset;

use App\Asset\Price\PriceDiff;
use App\Asset\Price\SummaryPrice;
use App\Crypto\Position\CryptoAssetPositionDetailDTO;
use Nette\Utils\Strings;

class CryptoAssetDetailDTO
{

	/**
	 * @param array<CryptoAssetPositionDetailDTO> $positions
	 */
	public function __construct(
		private readonly CryptoAsset $cryptoAsset,
		private readonly array $positions,
		private readonly SummaryPrice $totalInvestedAmount,
		private readonly SummaryPrice $currentAmount,
		private readonly SummaryPrice $currentAmountInBrokerCurrency,
		private readonly SummaryPrice $totalInvestedAmountInBrokerCurrency,
		private readonly PriceDiff $currentPriceDiff,
		private readonly PriceDiff $currentPriceDiffInBrokerCurrency,
		private readonly SummaryPrice $currentPriceInCzk,
		private readonly PriceDiff $currentPriceDiffInFromBrokerCurrencyToCzk,
		private readonly float $piecesCount,
	)
	{
	}

	public function getCryptoAsset(): CryptoAsset
	{
		return $this->cryptoAsset;
	}

	/**
	 * @return array<CryptoAssetPositionDetailDTO>
	 */
	public function getPositions(): array
	{
		return $this->positions;
	}

	public function getTotalInvestedAmount(): SummaryPrice
	{
		return $this->totalInvestedAmount;
	}

	public function getCurrentAmount(): SummaryPrice
	{
		return $this->currentAmount;
	}

	public function getCurrentAmountInBrokerCurrency(): SummaryPrice
	{
		return $this->currentAmountInBrokerCurrency;
	}

	public function getTotalInvestedAmountInBrokerCurrency(): SummaryPrice
	{
		return $this->totalInvestedAmountInBrokerCurrency;
	}

	public function getCurrentPriceDiff(): PriceDiff
	{
		return $this->currentPriceDiff;
	}

	public function getCurrentPriceDiffInBrokerCurrency(): PriceDiff
	{
		return $this->currentPriceDiffInBrokerCurrency;
	}

	public function getCurrentPriceInCzk(): SummaryPrice
	{
		return $this->currentPriceInCzk;
	}

	public function getCurrentPriceDiffInFromBrokerCurrencyToCzk(): PriceDiff
	{
		return $this->currentPriceDiffInFromBrokerCurrencyToCzk;
	}

	public function getPiecesCount(): float
	{
		return $this->piecesCount;
	}

	public function getHtmlTarget(): string
	{
		return Strings::webalize($this->cryptoAsset->getId()->toString());
	}

}
