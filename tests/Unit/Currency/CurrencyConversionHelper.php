<?php

declare(strict_types = 1);


namespace App\Test\Unit\Currency;


use App\Currency\CurrencyConversion;
use App\Currency\CurrencyConversionRepository;
use App\Currency\CurrencyEnum;
use App\Currency\CurrencySourceEnum;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;


class CurrencyConversionHelper
{
	public const USD_EUR = 0.9461;

	public const EUR_USD = 1.057;

	public const CZK_USD = 0.0447;

	public const USD_CZK = 22.369;

	public const CZK_EUR = 0.0423;

	public const EUR_CZK = 23.64;

	public static function getConversionMockRepository(): CurrencyConversionRepository
	{
		$mock = Mockery::mock(CurrencyConversionRepository::class)->makePartial();

		$mock->expects('getCurrentCurrencyPairConversion')
			->with(CurrencyEnum::USD, CurrencyEnum::EUR)
			->andReturns(self::getCurrencyConversion(
				CurrencyEnum::USD,
				CurrencyEnum::EUR,
				self::USD_EUR,
				CurrencySourceEnum::ECB
			));

		$mock->expects('getCurrentCurrencyPairConversion')
			->with(CurrencyEnum::EUR, CurrencyEnum::USD)
			->andReturns(self::getCurrencyConversion(
				CurrencyEnum::EUR,
				CurrencyEnum::USD,
				self::EUR_USD,
				CurrencySourceEnum::ECB
			));

		$mock->expects('getCurrentCurrencyPairConversion')
			->with(CurrencyEnum::CZK, CurrencyEnum::USD)
			->andReturns(self::getCurrencyConversion(
				CurrencyEnum::CZK,
				CurrencyEnum::USD,
				self::CZK_USD,
				CurrencySourceEnum::CNB
			));

		$mock->expects('getCurrentCurrencyPairConversion')
			->with(CurrencyEnum::USD, CurrencyEnum::CZK)
			->andReturns(self::getCurrencyConversion(
				CurrencyEnum::USD,
				CurrencyEnum::CZK,
				self::USD_CZK,
				CurrencySourceEnum::CNB
			));

		$mock->expects('getCurrentCurrencyPairConversion')
			->with(CurrencyEnum::CZK, CurrencyEnum::EUR)
			->andReturns(self::getCurrencyConversion(
				CurrencyEnum::CZK,
				CurrencyEnum::EUR,
				self::CZK_EUR,
				CurrencySourceEnum::CNB
			));

		$mock->expects('getCurrentCurrencyPairConversion')
			->with(CurrencyEnum::EUR, CurrencyEnum::CZK)
			->andReturns(self::getCurrencyConversion(
				CurrencyEnum::EUR,
				CurrencyEnum::CZK,
				self::EUR_CZK,
				CurrencySourceEnum::CNB
			));

		return $mock;
	}

	private static function getCurrencyConversion(
		CurrencyEnum $fromCurrency,
		CurrencyEnum $toCurrency,
		float $currentPrice,
		CurrencySourceEnum $source
	): CurrencyConversion
	{
		return new CurrencyConversion(
			$fromCurrency,
			$toCurrency,
			$currentPrice,
			$source,
			new ImmutableDateTime(),
			new ImmutableDateTime()
		);
	}
}
