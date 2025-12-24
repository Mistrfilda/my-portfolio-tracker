<?php

declare(strict_types = 1);

namespace App\Stock\Position\Closed;

use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class StockClosedPositionStatisticFacade
{

	public function __construct(
		private StockClosedPositionRepository $stockClosedPositionRepository,
		private CurrencyConversionFacade $currencyConversionFacade,
	)
	{
	}

	public function calculateProfitInPeriod(
		ImmutableDateTime $start,
		ImmutableDateTime $end,
	): float
	{
		$closedPositions = $this->stockClosedPositionRepository->findBetweenDates($start, $end);

		$totalProfit = 0.0;
		foreach ($closedPositions as $closedPosition) {
			// Získáme prodejní cenu v broker měně
			$sellPrice = $closedPosition->getTotalCloseAmountInBrokerCurrency();

			// Získáme nákupní cenu v broker měně z pozice
			$stockPosition = $closedPosition->getAssetPositon();
			$buyPrice = $stockPosition->getTotalInvestedAmountInBrokerCurrency();

			// Konverze prodejní ceny do CZK
			$sellPriceCzk = $this->currencyConversionFacade->getConvertedAssetPrice(
				$sellPrice,
				CurrencyEnum::CZK,
				$closedPosition->getDate(),
			);

			// Konverze nákupní ceny do CZK
			$buyPriceCzk = $this->currencyConversionFacade->getConvertedAssetPrice(
				$buyPrice,
				CurrencyEnum::CZK,
				$stockPosition->getOrderDate(),
			);

			$totalProfit += $sellPriceCzk->getPrice() - $buyPriceCzk->getPrice();
		}

		return $totalProfit;
	}

}
