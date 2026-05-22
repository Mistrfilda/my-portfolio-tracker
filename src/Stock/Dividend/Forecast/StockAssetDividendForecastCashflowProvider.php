<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Forecast;

use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Currency\MissingCurrencyPairException;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Dividend\StockAssetDividendTypeEnum;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class StockAssetDividendForecastCashflowProvider
{

	public function __construct(
		private CurrencyConversionFacade $currencyConversionFacade,
		private DatetimeFactory $datetimeFactory,
	)
	{
	}

	/**
	 * @return array<int, StockAssetDividendForecastCashflowMonth>
	 */
	public function getMonths(StockAssetDividendForecast $forecast): array
	{
		$months = [];
		for ($month = 1; $month <= 12; $month++) {
			$months[$month] = new StockAssetDividendForecastCashflowMonth($forecast->getForYear(), $month);
		}

		foreach ($forecast->getRecords() as $record) {
			$this->addRecordCashflow($months, $forecast, $record);
		}

		return array_values($months);
	}

	/**
	 * @param array<int, StockAssetDividendForecastCashflowMonth> $months
	 */
	private function addRecordCashflow(
		array $months,
		StockAssetDividendForecast $forecast,
		StockAssetDividendForecastRecord $record,
	): void
	{
		$receivedMonths = $this->filterMonths($record->getReceivedDividendMonths());
		$missedMonths = $this->getMissedAlreadyPaidDividendMonths($record);
		$estimatedMonths = array_values(array_diff(
			$this->filterMonths($record->getDividendUsuallyPaidAtMonths()),
			$receivedMonths,
			$missedMonths,
		));

		if ($receivedMonths !== []) {
			$this->addCashflowItems(
				$months,
				$forecast,
				$record,
				$receivedMonths,
				$record->getActualReceivedTotal(),
				$record->getActualReceivedTotalBeforeTax(),
				true,
			);
		}

		if ($estimatedMonths !== []) {
			$this->addCashflowItems(
				$months,
				$forecast,
				$record,
				$estimatedMonths,
				$record->getRemainingDividendTotal(),
				$record->getRemainingDividendTotalBeforeTax(),
				false,
			);
		}
	}

	/**
	 * @param array<int, StockAssetDividendForecastCashflowMonth> $months
	 * @param array<int> $cashflowMonths
	 */
	private function addCashflowItems(
		array $months,
		StockAssetDividendForecast $forecast,
		StockAssetDividendForecastRecord $record,
		array $cashflowMonths,
		float $netAmount,
		float $grossAmount,
		bool $confirmed,
	): void
	{
		if ($netAmount <= 0.0 && $grossAmount <= 0.0) {
			return;
		}

		$monthsCount = count($cashflowMonths);
		$netAmountPerMonth = $netAmount / $monthsCount;
		$grossAmountPerMonth = $grossAmount / $monthsCount;

		foreach ($cashflowMonths as $month) {
			$months[$month]->addItem(new StockAssetDividendForecastCashflowItem(
				$record->getStockAsset(),
				$record->getCurrency(),
				$netAmountPerMonth,
				$grossAmountPerMonth,
				$this->convertToCzk($netAmountPerMonth, $record->getCurrency(), $forecast->getForYear(), $month),
				$this->convertToCzk($grossAmountPerMonth, $record->getCurrency(), $forecast->getForYear(), $month),
				$confirmed,
			));
		}
	}

	private function convertToCzk(float $amount, CurrencyEnum $currency, int $year, int $month): float
	{
		if ($currency === CurrencyEnum::CZK) {
			return $amount;
		}

		try {
			return $this->currencyConversionFacade->convertSimpleValue(
				$amount,
				$currency,
				CurrencyEnum::CZK,
				new ImmutableDateTime(sprintf('%d-%02d-01', $year, $month)),
			);
		} catch (MissingCurrencyPairException) {
			return 0.0;
		}
	}

	/**
	 * @param array<int> $months
	 * @return array<int>
	 */
	private function filterMonths(array $months): array
	{
		$filteredMonths = [];
		foreach ($months as $month) {
			if ($month < 1 || $month > 12) {
				continue;
			}

			$filteredMonths[] = $month;
		}

		return array_values(array_unique($filteredMonths));
	}

	/**
	 * @return array<int>
	 */
	private function getMissedAlreadyPaidDividendMonths(StockAssetDividendForecastRecord $record): array
	{
		$months = [];
		$now = $this->datetimeFactory->createNow();
		$forYear = $record->getStockAssetDividendForecast()->getForYear();

		foreach ($record->getStockAsset()->getDividends() as $dividend) {
			if ($dividend->getExDate()->getYear() !== $forYear) {
				continue;
			}

			if ($dividend->getDividendType() !== StockAssetDividendTypeEnum::REGULAR) {
				continue;
			}

			if (count($dividend->getRecords()) > 0 || $this->isDividendAlreadyPaid($dividend, $now) === false) {
				continue;
			}

			$months[] = $dividend->getExDate()->getMonth();
		}

		return $this->filterMonths($months);
	}

	private function isDividendAlreadyPaid(StockAssetDividend $dividend, ImmutableDateTime $now): bool
	{
		$paymentDate = $dividend->getPaymentDate();
		if ($paymentDate !== null) {
			return $paymentDate <= $now;
		}

		return $dividend->getExDate() <= $now;
	}

}
