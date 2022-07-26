<?php

declare(strict_types = 1);

namespace App\Currency;

use App\Asset\Price\AssetPrice;
use App\Asset\Price\PriceDiff;
use App\Asset\Price\SummaryPrice;

class CurrencyConversionFacade
{

	public function __construct(
		private readonly CurrencyConversionRepository $currencyConversionRepository,
	)
	{
	}

	public function getConvertedAssetPrice(
		AssetPrice $assetPriceForConvert,
		CurrencyEnum $toCurrency,
	): AssetPrice
	{
		if ($assetPriceForConvert->getCurrency() === $toCurrency) {
			return $assetPriceForConvert;
		}

		$currencyConversion = $this->currencyConversionRepository->getCurrentCurrencyPairConversion(
			$assetPriceForConvert->getCurrency(),
			$toCurrency,
		);

		return new AssetPrice(
			$assetPriceForConvert->getAsset(),
			$this->convertPrice($assetPriceForConvert->getPrice(), $currencyConversion),
			$toCurrency,
		);
	}

	public function getConvertedSummaryPrice(
		SummaryPrice $summaryPrice,
		CurrencyEnum $toCurrency,
	): SummaryPrice
	{
		if ($summaryPrice->getCurrency() === $toCurrency) {
			return $summaryPrice;
		}

		$currencyConversion = $this->currencyConversionRepository->getCurrentCurrencyPairConversion(
			$summaryPrice->getCurrency(),
			$toCurrency,
		);

		return new SummaryPrice(
			$toCurrency,
			$this->convertPrice($summaryPrice->getPrice(), $currencyConversion),
			$summaryPrice->getCounter(),
		);
	}

	public function getConvertedPriceDiff(
		PriceDiff $priceDiff,
		CurrencyEnum $toCurrency,
	): PriceDiff
	{
		if ($priceDiff->getCurrencyEnum() === $toCurrency) {
			return $priceDiff;
		}

		$currencyConversion = $this->currencyConversionRepository->getCurrentCurrencyPairConversion(
			$priceDiff->getCurrencyEnum(),
			$toCurrency,
		);

		return new PriceDiff(
			$this->convertPrice($priceDiff->getPriceDifference(), $currencyConversion),
			$priceDiff->getRawPercentageDifference(),
			$toCurrency,
		);
	}

	private function convertPrice(float $price, CurrencyConversion $currencyConversion): float
	{
		return $price * $currencyConversion->getCurrentPrice();
	}

}
