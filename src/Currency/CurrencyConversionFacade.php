<?php

declare(strict_types = 1);

namespace App\Currency;

use App\Asset\Price\AssetPrice;
use App\Asset\Price\PriceDiff;
use App\Asset\Price\SummaryPrice;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

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
		ImmutableDateTime|null $forDate = null,
	): AssetPrice
	{
		if ($assetPriceForConvert->getCurrency() === $toCurrency) {
			return $assetPriceForConvert;
		}

		if ($forDate === null) {
			$currencyConversion = $this->currencyConversionRepository->getCurrentCurrencyPairConversion(
				$assetPriceForConvert->getCurrency(),
				$toCurrency,
			);
		} else {
			$currencyConversion = $this->currencyConversionRepository->findCurrencyPairConversionForClosestDate(
				$assetPriceForConvert->getCurrency(),
				$toCurrency,
				$forDate,
			);
		}

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
