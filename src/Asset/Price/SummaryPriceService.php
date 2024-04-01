<?php

declare(strict_types = 1);

namespace App\Asset\Price;

use App\Asset\Position\AssetPosition;
use App\Asset\Price\Exception\PriceDiffException;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;

class SummaryPriceService
{

	public function __construct(
		private readonly CurrencyConversionFacade $currencyConversionFacade,
	)
	{
	}

	public function getSummaryPriceDiff(
		SummaryPrice $summaryPrice1,
		SummaryPrice $summaryPrice2,
	): PriceDiff
	{
		if ($summaryPrice1->getCurrency() !== $summaryPrice2->getCurrency()) {
			throw new PriceDiffException('Currency must be same');
		}

		$diffPrice = $summaryPrice1->getPrice() - $summaryPrice2->getPrice();

		if ($summaryPrice2->getPrice() === 0.0) {
			$percentageDiff = 200;
		} else {
			$percentageDiff = $summaryPrice1->getPrice() * 100 / $summaryPrice2->getPrice();
		}

		return new PriceDiff(
			$diffPrice,
			$percentageDiff,
			$summaryPrice1->getCurrency(),
		);
	}

	/**
	 * @param array<AssetPosition> $positions
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
	 * @param array<AssetPosition> $positions
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
						$position->getOrderDate(),
					),
				);

				continue;
			}

			$summaryPrice->addAssetPrice($currentTotalAmount);
		}

		return $summaryPrice;
	}

	/**
	 * @param array<AssetPosition> $positions
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
						$position->getOrderDate(),
					),
				);

				continue;
			}

			$summaryPrice->addAssetPrice($currentTotalAmount);
		}

		return $summaryPrice;
	}

}
