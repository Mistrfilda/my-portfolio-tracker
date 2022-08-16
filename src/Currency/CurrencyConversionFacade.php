<?php

declare(strict_types = 1);

namespace App\Currency;

use App\Asset\Price\AssetPrice;

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

	private function convertPrice(float $price, CurrencyConversion $currencyConversion): float
	{
		return $price * $currencyConversion->getCurrentPrice();
	}

}
