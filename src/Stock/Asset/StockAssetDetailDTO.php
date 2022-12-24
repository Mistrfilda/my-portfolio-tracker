<?php

declare(strict_types = 1);

namespace App\Stock\Asset;

use App\Asset\Price\PriceDiff;
use App\Asset\Price\SummaryPrice;
use App\Stock\Position\StockAssetPositionDetailDTO;
use Nette\Utils\Strings;

class StockAssetDetailDTO
{

	/**
	 * @param array<StockAssetPositionDetailDTO> $positions
	 */
	public function __construct(
		private readonly StockAsset $stockAsset,
		private readonly array $positions,
		private readonly SummaryPrice $totalInvestedAmount,
		private readonly SummaryPrice $currentAmount,
		private readonly SummaryPrice $totalInvestedAmountInBrokerCurrency,
		private readonly PriceDiff $currentPriceDiff,
		private readonly PriceDiff $currentPriceDiffInBrokerCurrency,
		private readonly SummaryPrice $currentPriceInCzk,
		private readonly PriceDiff $currentPriceDiffInFromBrokerCurrencyToCzk,
		private readonly int $piecesCount,
	)
	{
	}

	public function getStockAsset(): StockAsset
	{
		return $this->stockAsset;
	}

	/**
	 * @return array<StockAssetPositionDetailDTO>
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

	public function getPiecesCount(): int
	{
		return $this->piecesCount;
	}

	public function getHtmlTarget(): string
	{
		return Strings::webalize($this->stockAsset->getId()->toString());
	}

}
