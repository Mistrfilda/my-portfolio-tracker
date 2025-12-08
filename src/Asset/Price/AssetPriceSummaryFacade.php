<?php

declare(strict_types = 1);

namespace App\Asset\Price;

use App\Currency\CurrencyEnum;

class AssetPriceSummaryFacade
{

	/** @var array<AssetPriceFacade> */
	private array $assetPriceFacades;

	/**
	 * @param array<AssetPriceFacade> $assetPriceFacades
	 */
	public function __construct(
		array $assetPriceFacades,
		private readonly SummaryPriceService $summaryPriceService,
	)
	{
		$this->assetPriceFacades = $assetPriceFacades;
	}

	public function getTotalInvestedAmount(CurrencyEnum $inCurrency): SummaryPrice
	{
		$summaryPrice = new SummaryPrice($inCurrency);
		foreach ($this->assetPriceFacades as $assetPriceFacade) {
			if ($assetPriceFacade->includeToTotalValues() === false) {
				continue;
			}

			$summaryPrice->addSummaryPrice($assetPriceFacade->getTotalInvestedAmountSummaryPrice(
				$inCurrency,
			));
		}

		return $summaryPrice;
	}

	public function getCurrentValue(CurrencyEnum $inCurrency): SummaryPrice
	{
		$summaryPrice = new SummaryPrice($inCurrency);
		foreach ($this->assetPriceFacades as $assetPriceFacade) {
			if ($assetPriceFacade->includeToTotalValues() === false) {
				continue;
			}

			$summaryPrice->addSummaryPrice($assetPriceFacade->getCurrentPortfolioValueSummaryPrice(
				$inCurrency,
			));
		}

		return $summaryPrice;
	}

	public function getTotalPriceDiff(CurrencyEnum $inCurrency): PriceDiff
	{
		return $this->summaryPriceService->getSummaryPriceDiff(
			$this->getCurrentValue($inCurrency),
			$this->getTotalInvestedAmount($inCurrency),
		);
	}

}
