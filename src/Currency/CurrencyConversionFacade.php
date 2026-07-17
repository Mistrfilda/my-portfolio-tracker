<?php

declare(strict_types = 1);

namespace App\Currency;

use App\Asset\Price\AssetPrice;
use App\Asset\Price\PriceDiff;
use App\Asset\Price\SummaryPrice;
use Doctrine\ORM\NoResultException;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class CurrencyConversionFacade
{

	/** @var array<string, CurrencyConversion> */
	private array $currencyConversions = [];

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

		$currencyConversion = $this->getCurrencyConversion(
			$assetPriceForConvert->getCurrency(),
			$toCurrency,
			$forDate,
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
		ImmutableDateTime|null $forDate = null,
	): SummaryPrice
	{
		if ($summaryPrice->getCurrency() === $toCurrency) {
			return $summaryPrice;
		}

		$currencyConversion = $this->getCurrencyConversion(
			$summaryPrice->getCurrency(),
			$toCurrency,
			$forDate,
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

		$currencyConversion = $this->getCurrencyConversion(
			$priceDiff->getCurrencyEnum(),
			$toCurrency,
			null,
		);

		return new PriceDiff(
			$this->convertPrice($priceDiff->getPriceDifference(), $currencyConversion),
			$priceDiff->getRawPercentageDifference(),
			$toCurrency,
		);
	}

	public function convertSimpleValue(
		float $price,
		CurrencyEnum $fromCurrency,
		CurrencyEnum $toCurrency,
		ImmutableDateTime|null $forDate = null,
	): float
	{
		try {
			$currencyConversion = $this->getCurrencyConversion(
				$fromCurrency,
				$toCurrency,
				$forDate,
			);
		} catch (NoResultException $e) {
			throw new MissingCurrencyPairException(previous: $e);
		}

		return $this->convertPrice($price, $currencyConversion);
	}

	private function getCurrencyConversion(
		CurrencyEnum $fromCurrency,
		CurrencyEnum $toCurrency,
		ImmutableDateTime|null $forDate,
	): CurrencyConversion
	{
		$key = sprintf(
			'%s:%s:%s',
			$fromCurrency->value,
			$toCurrency->value,
			$forDate?->format('Y-m-d H:i:s.uP') ?? 'current',
		);

		if (isset($this->currencyConversions[$key])) {
			return $this->currencyConversions[$key];
		}

		if ($forDate === null) {
			$currencyConversion = $this->currencyConversionRepository->getCurrentCurrencyPairConversion(
				$fromCurrency,
				$toCurrency,
			);
		} else {
			$currencyConversion = $this->currencyConversionRepository->findCurrencyPairConversionForClosestDate(
				$fromCurrency,
				$toCurrency,
				$forDate,
			);
		}

		$this->currencyConversions[$key] = $currencyConversion;

		return $currencyConversion;
	}

	private function convertPrice(float $price, CurrencyConversion $currencyConversion): float
	{
		return $price * $currencyConversion->getCurrentPrice();
	}

}
