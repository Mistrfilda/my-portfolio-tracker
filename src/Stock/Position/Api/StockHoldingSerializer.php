<?php

declare(strict_types = 1);

namespace App\Stock\Position\Api;

use App\Asset\Price\PriceDiff;
use App\Stock\Asset\StockAssetDetailDTO;
use App\Stock\Dividend\UI\StockAssetDividendDetailDTO;
use App\Stock\Position\StockAssetPositionDetailDTO;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use const DATE_ATOM;

class StockHoldingSerializer
{

	/** @return array<string, mixed> */
	public function serialize(
		StockAssetDetailDTO $detail,
		StockAssetDividendDetailDTO $dividends,
		ImmutableDateTime $dividendsFrom,
		float $portfolioValueCzk,
	): array
	{
		$stock = $detail->getStockAsset();
		$price = $stock->getAssetCurrentPrice()->getPrice();
		$dividendPerShare = $dividends->getDividendsSummaryPriceWithTax()->getPrice();
		$averagePurchasePrice = $detail->getAveragePurchasePrice();
		$valueCzk = $detail->getCurrentPriceInCzk()->getPrice();
		return [
			'stock' => [
				'id' => $stock->getId()->toString(),
				'name' => $stock->getName(),
				'ticker' => $stock->getTicker(),
				'exchange' => $stock->getExchange()->format(),
				'currency' => $stock->getCurrency()->value,
				'currentPrice' => $price,
				'priceUpdatedAt' => $stock->getPriceDownloadedAt()?->format(DATE_ATOM),
			],
			'pieces' => $detail->getPiecesCount(),
			'averagePurchasePrice' => $averagePurchasePrice,
			'amounts' => $this->amounts(
				$detail->getTotalInvestedAmount()->getPrice(),
				$detail->getCurrentAmount()->getPrice(),
				$detail->getCurrentPriceDiff(),
			),
			'brokerCurrency' => $detail->getCurrentAmountInBrokerCurrency()->getCurrency()->value,
			'brokerAmounts' => $this->amounts(
				$detail->getTotalInvestedAmountInBrokerCurrency()->getPrice(),
				$detail->getCurrentAmountInBrokerCurrency()->getPrice(),
				$detail->getCurrentPriceDiffInBrokerCurrency(),
			),
			'currentValueCzk' => $valueCzk,
			'portfolioSharePercent' => $portfolioValueCzk === 0.0 ? 0.0 : $valueCzk / $portfolioValueCzk * 100,
			'dividends' => [
				'fromDate' => $dividendsFrom->format('Y-m-d'),
				'grossPerShare' => $dividendPerShare,
				'yieldOnPricePercent' => $price === 0.0 ? null : $dividendPerShare / $price * 100,
				'yieldOnCostPercent' => $averagePurchasePrice === 0.0 ? null : $dividendPerShare / $averagePurchasePrice * 100,
			],
			'positions' => array_map($this->position(...), $detail->getPositions()),
		];
	}

	/** @return array<string, mixed> */
	private function position(StockAssetPositionDetailDTO $detail): array
	{
		$position = $detail->getStockPosition();
		$brokerCost = $position->getTotalInvestedAmountInBrokerCurrency();
		return [
			'id' => $position->getId()->toString(),
			'orderDate' => $position->getOrderDate()->format('Y-m-d'),
			'pieces' => $position->getOrderPiecesCount(),
			'purchasePrice' => $position->getPricePerPiece()->getPrice(),
			'amounts' => $this->amounts(
				$position->getTotalInvestedAmount()->getPrice(),
				$position->getCurrentTotalAmount()->getPrice(),
				$detail->getPriceDiff(),
			),
			'brokerCost' => ['amount' => $brokerCost->getPrice(), 'currency' => $brokerCost->getCurrency()->value],
		];
	}

	/** @return array{investedAmount: float, currentValue: float, profit: float, profitPercent: float} */
	private function amounts(float $invested, float $current, PriceDiff $diff): array
	{
		return [
			'investedAmount' => $invested,
			'currentValue' => $current,
			'profit' => $diff->getPriceDifference(),
			'profitPercent' => $diff->getPercentageDifference(),
		];
	}

}
