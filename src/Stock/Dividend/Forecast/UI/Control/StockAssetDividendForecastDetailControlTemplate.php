<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Forecast\UI\Control;

use App\Asset\Price\SummaryPrice;
use App\Currency\CurrencyEnum;
use App\Stock\Dividend\Forecast\StockAssetDividendForecast;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastCashflowMonth;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRecord;
use App\UI\Base\BaseControlTemplate;

class StockAssetDividendForecastDetailControlTemplate extends BaseControlTemplate
{

	public StockAssetDividendForecast $forecast;

	/** @var array<StockAssetDividendForecastRecord> */
	public array $records;

	/** @var array<StockAssetDividendForecastCashflowMonth> */
	public array $cashflowMonths;

	public CurrencyEnum $czkCurrency;

	/**
	 * @var array<string, array{
	 *     currency: CurrencyEnum,
	 *     alreadyReceived: float,
	 *     totalYear: float,
	 *     remaining: float,
	 *     alreadyReceivedBeforeTax: float,
	 *     totalYearBeforeTax: float,
	 *     remainingBeforeTax: float,
	 * }>
	 */
	public array $totalsByCurrency;

	public SummaryPrice $czkTotalAlreadyReceived;

	public SummaryPrice $czkTotalRemaining;

	public SummaryPrice $czkTotalYear;

	public SummaryPrice $czkTotalAlreadyReceivedBeforeTax;

	public SummaryPrice $czkTotalRemainingBeforeTax;

	public SummaryPrice $czkTotalYearBeforeTax;

}
