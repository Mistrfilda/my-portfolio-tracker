<?php

declare(strict_types = 1);

namespace App\Tax;

use App\Currency\CurrencyConversionRepository;
use App\Currency\CurrencyEnum;
use App\Currency\CurrencySourceEnum;
use App\Tax\Calculator\TaxMath;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class TaxCurrencyConverter
{

	public function __construct(
		private CurrencyConversionRepository $currencyConversionRepository,
	)
	{
	}

	/**
	 * Converts an amount in the given currency to CZK using the latest stored CNB rate on or before $date.
	 *
	 * @return array{amountCzk: int, rate: float|null, rateDate: string|null, missingRate: bool}
	 */
	public function convertToCzk(
		float $amount,
		CurrencyEnum $currency,
		ImmutableDateTime $date,
	): array
	{
		if ($currency === CurrencyEnum::CZK) {
			return [
				'amountCzk' => TaxMath::roundCzk($amount),
				'rate' => 1.0,
				'rateDate' => $date->format('Y-m-d'),
				'missingRate' => false,
			];
		}

		$conversion = $this->currencyConversionRepository->findClosestBeforeForSource(
			$currency,
			CurrencyEnum::CZK,
			$date,
			CurrencySourceEnum::CNB,
		);

		if ($conversion === null) {
			return [
				'amountCzk' => 0,
				'rate' => null,
				'rateDate' => null,
				'missingRate' => true,
			];
		}

		$rate = $conversion->getCurrentPrice();

		return [
			'amountCzk' => TaxMath::roundCzk($amount * $rate),
			'rate' => $rate,
			'rateDate' => $conversion->getForDate()->format('Y-m-d'),
			'missingRate' => false,
		];
	}

}
