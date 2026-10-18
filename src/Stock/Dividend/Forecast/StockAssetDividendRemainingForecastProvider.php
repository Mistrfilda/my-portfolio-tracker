<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Forecast;

use App\Admin\AppAdmin;
use App\Asset\Price\SummaryPrice;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Stock\Dividend\Record\StockAssetDividendYearSummaryDTO;

class StockAssetDividendRemainingForecastProvider
{

	public function __construct(
		private StockAssetDividendForecastRepository $forecasts,
		private CurrencyConversionFacade $conversions,
	)
	{
	}

	public function getForYear(int $year, AppAdmin $appAdmin): StockAssetDividendYearSummaryDTO|null
	{
		$forecast = $this->forecasts->findByDefaultForYear($year, $appAdmin);
		if ($forecast === null) {
			return null;
		}

		$net = new SummaryPrice(CurrencyEnum::CZK);
		$gross = new SummaryPrice(CurrencyEnum::CZK);
		foreach ($forecast->getRecords() as $record) {
			$remainingNet = $record->getRemainingDividendTotal();
			if ($remainingNet > 0) {
				$net->addSummaryPrice($this->convertToCzk($record->getCurrency(), $remainingNet));
			}

			$remainingGross = $record->getRemainingDividendTotalBeforeTax();
			if ($remainingGross > 0) {
				$gross->addSummaryPrice($this->convertToCzk($record->getCurrency(), $remainingGross));
			}
		}

		return new StockAssetDividendYearSummaryDTO($year, $net, $gross);
	}

	private function convertToCzk(CurrencyEnum $currency, float $amount): SummaryPrice
	{
		$price = new SummaryPrice($currency, $amount);
		return $currency === CurrencyEnum::CZK
			? $price
			: $this->conversions->getConvertedSummaryPrice($price, CurrencyEnum::CZK);
	}

}
