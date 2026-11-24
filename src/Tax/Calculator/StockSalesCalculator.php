<?php

declare(strict_types = 1);

namespace App\Tax\Calculator;

use App\Tax\DTO\TaxStockSaleItemDTO;
use App\Tax\DTO\TaxStockSalesSectionDTO;
use App\Tax\Rules\TaxYearRules;
use DateTimeImmutable;

class StockSalesCalculator
{

	/**
	 * @param list<TaxStockSaleInput> $sales
	 */
	public function calculate(array $sales, TaxYearRules $rules): TaxStockSalesSectionDTO
	{
		$totalProceedsCzk = 0;
		foreach ($sales as $sale) {
			$totalProceedsCzk += $sale->sellAmountCzk;
		}

		$passedValueTest = $totalProceedsCzk <= $rules->stockAnnualProceedsExemptionLimitCzk;

		$items = [];
		$timeTestExemptProceedsCzk = 0;
		$taxableProceedsCzk = 0;
		$taxableExpensesCzk = 0;
		$notification38vItemCount = 0;

		foreach ($sales as $sale) {
			$holdingDays = (int) $sale->buyDate->diff($sale->sellDate)->format('%a');
			$passedTimeTest = $this->hasPassedTimeTest($sale->buyDate, $sale->sellDate, $rules->stockTimeTestYears);

			$isExempt = false;
			$exemptionReason = null;

			if ($passedValueTest) {
				$isExempt = true;
				$exemptionReason = sprintf(
					'Hodnotový test (obrat do %s Kč)',
					number_format($rules->stockAnnualProceedsExemptionLimitCzk, 0, ',', ' '),
				);
			} elseif ($passedTimeTest) {
				$isExempt = true;
				$exemptionReason = sprintf('Časový test (drženo déle než %d roky)', $rules->stockTimeTestYears);
				$timeTestExemptProceedsCzk += $sale->sellAmountCzk;
			} else {
				$taxableProceedsCzk += $sale->sellAmountCzk;
				$taxableExpensesCzk += $sale->buyAmountCzk;
			}

			$requiresNotification38v = $isExempt && $sale->sellAmountCzk > $rules->exemptIncomeNotificationThresholdCzk;
			if ($requiresNotification38v) {
				$notification38vItemCount++;
			}

			$items[] = new TaxStockSaleItemDTO(
				closedPositionId: $sale->closedPositionId,
				assetName: $sale->assetName,
				ticker: $sale->ticker,
				pieces: $sale->pieces,
				buyDate: $sale->buyDate->format('Y-m-d'),
				sellDate: $sale->sellDate->format('Y-m-d'),
				holdingDays: $holdingDays,
				passedTimeTest: $passedTimeTest,
				buyCurrency: $sale->buyCurrency,
				buyAmountOriginal: $sale->buyAmountOriginal,
				buyExchangeRate: $sale->buyExchangeRate,
				buyExchangeRateDate: $sale->buyExchangeRateDate,
				buyAmountCzk: $sale->buyAmountCzk,
				sellCurrency: $sale->sellCurrency,
				sellAmountOriginal: $sale->sellAmountOriginal,
				sellExchangeRate: $sale->sellExchangeRate,
				sellExchangeRateDate: $sale->sellExchangeRateDate,
				sellAmountCzk: $sale->sellAmountCzk,
				gainCzk: $sale->sellAmountCzk - $sale->buyAmountCzk,
				isExempt: $isExempt,
				exemptionReason: $exemptionReason,
				requiresNotification38v: $requiresNotification38v,
				hasMissingExchangeRate: $sale->hasMissingExchangeRate,
			);
		}

		$exceededTimeTestCap = $rules->stockTimeExemptionAnnualCapCzk !== null
			&& $timeTestExemptProceedsCzk > $rules->stockTimeExemptionAnnualCapCzk;

		$rawTaxableGainCzk = $taxableProceedsCzk - $taxableExpensesCzk;
		$taxBaseSection10Czk = max(0, $rawTaxableGainCzk);

		return new TaxStockSalesSectionDTO(
			totalProceedsCzk: $totalProceedsCzk,
			proceedsExemptionLimitCzk: $rules->stockAnnualProceedsExemptionLimitCzk,
			passedValueTest: $passedValueTest,
			timeTestExemptProceedsCzk: $timeTestExemptProceedsCzk,
			timeTestExemptionCapCzk: $rules->stockTimeExemptionAnnualCapCzk,
			exceededTimeTestCap: $exceededTimeTestCap,
			taxableProceedsCzk: $taxableProceedsCzk,
			taxableExpensesCzk: $taxableExpensesCzk,
			rawTaxableGainCzk: $rawTaxableGainCzk,
			taxBaseSection10Czk: $taxBaseSection10Czk,
			notification38vItemCount: $notification38vItemCount,
			items: $items,
		);
	}

	public function hasPassedTimeTest(
		DateTimeImmutable $buyDate,
		DateTimeImmutable $sellDate,
		int $years,
	): bool
	{
		$buyYear = (int) $buyDate->format('Y');
		$buyMonth = (int) $buyDate->format('n');
		$buyDay = (int) $buyDate->format('j');

		$targetYear = $buyYear + $years;
		$daysInTargetMonth = (int) new DateTimeImmutable(sprintf('%04d-%02d-01', $targetYear, $buyMonth))->format('t');
		$anniversaryDay = min($buyDay, $daysInTargetMonth);
		$anniversaryDate = sprintf('%04d-%02d-%02d', $targetYear, $buyMonth, $anniversaryDay);

		return $sellDate->format('Y-m-d') > $anniversaryDate;
	}

}
