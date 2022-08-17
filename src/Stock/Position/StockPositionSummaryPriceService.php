<?php

declare(strict_types = 1);

namespace App\Stock\Position;

use App\Asset\Price\SummaryPrice;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;

class StockPositionSummaryPriceService
{

	public function __construct(
		private readonly CurrencyConversionFacade $currencyConversionFacade,
	)
	{
	}

	/**
	 * @param array<StockPosition> $positions
	 */
	public function getSummaryPriceForPositions(
		CurrencyEnum $inCurrency,
		array $positions,
	): SummaryPrice
	{
		$summaryPrice = new SummaryPrice($inCurrency);

		foreach ($positions as $position) {
			$currentTotalAmount = $position->getCurrentTotalAmount();
			if ($currentTotalAmount->getCurrency() !== $summaryPrice->getCurrency()) {
				$summaryPrice->addAssetPrice(
					$this->currencyConversionFacade->getConvertedAssetPrice(
						$currentTotalAmount,
						$summaryPrice->getCurrency(),
					),
				);

				continue;
			}

			$summaryPrice->addAssetPrice($currentTotalAmount);
		}

		return $summaryPrice;
	}

	/**
	 * @param array<StockPosition> $positions
	 */
	public function getSummaryPriceForTotalInvestedAmount(
		CurrencyEnum $inCurrency,
		array $positions,
	): SummaryPrice
	{
		$summaryPrice = new SummaryPrice($inCurrency);

		foreach ($positions as $position) {
			$currentTotalAmount = $position->getTotalInvestedAmount();
			if ($currentTotalAmount->getCurrency() !== $summaryPrice->getCurrency()) {
				$summaryPrice->addAssetPrice(
					$this->currencyConversionFacade->getConvertedAssetPrice(
						$currentTotalAmount,
						$summaryPrice->getCurrency(),
					),
				);

				continue;
			}

			$summaryPrice->addAssetPrice($currentTotalAmount);
		}

		return $summaryPrice;
	}

	/**
	 * @param array<StockPosition> $positions
	 */
	public function getSummaryPriceForTotalInvestedAmountInBrokerCurrency(
		CurrencyEnum $inCurrency,
		array $positions,
	): SummaryPrice
	{
		$summaryPrice = new SummaryPrice($inCurrency);

		foreach ($positions as $position) {
			$currentTotalAmount = $position->getTotalInvestedAmountInBrokerCurrency();
			if ($currentTotalAmount->getCurrency() !== $summaryPrice->getCurrency()) {
				$summaryPrice->addAssetPrice(
					$this->currencyConversionFacade->getConvertedAssetPrice(
						$currentTotalAmount,
						$summaryPrice->getCurrency(),
					),
				);

				continue;
			}

			$summaryPrice->addAssetPrice($currentTotalAmount);
		}

		return $summaryPrice;
	}

}
