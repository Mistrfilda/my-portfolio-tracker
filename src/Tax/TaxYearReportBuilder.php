<?php

declare(strict_types = 1);

namespace App\Tax;

use App\Admin\AppAdmin;
use App\Cash\Expense\Bank\BankExpense;
use App\Cash\Expense\Bank\BankExpenseRepository;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncome;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncomeRepository;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Dividend\Record\StockAssetDividendRecordRepository;
use App\Stock\Position\Closed\StockClosedPositionRepository;
use App\Stock\Position\StockPosition;
use App\Tax\Calculator\BusinessIncomeCalculator;
use App\Tax\Calculator\DividendTaxCalculator;
use App\Tax\Calculator\HealthInsuranceCalculator;
use App\Tax\Calculator\IncomeTaxCalculator;
use App\Tax\Calculator\SocialInsuranceCalculator;
use App\Tax\Calculator\StockSalesCalculator;
use App\Tax\Calculator\TaxDividendInput;
use App\Tax\Calculator\TaxMath;
use App\Tax\Calculator\TaxStockSaleInput;
use App\Tax\DTO\TaxBusinessIncomeSectionDTO;
use App\Tax\DTO\TaxDonationItemDTO;
use App\Tax\DTO\TaxPaymentItemDTO;
use App\Tax\DTO\TaxStockSalesSectionDTO;
use App\Tax\DTO\TaxWorkIncomeMonthItemDTO;
use App\Tax\DTO\TaxYearReportSnapshotDTO;
use App\Tax\Payment\TaxPaymentRoleEnum;
use App\Tax\Rules\TaxInstitutionEnum;
use App\Tax\Rules\TaxTreatyRateProvider;
use App\Tax\Rules\TaxYearRules;
use App\Tax\Rules\TaxYearRulesProvider;
use App\Utils\Datetime\DatetimeConst;
use DateTimeImmutable;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class TaxYearReportBuilder
{

	public function __construct(
		private TaxYearProfileFacade $profileFacade,
		private TaxYearDonationRepository $donationRepository,
		private TaxYearReportRepository $reportRepository,
		private WorkMonthlyIncomeRepository $workMonthlyIncomeRepository,
		private BankExpenseRepository $bankExpenseRepository,
		private StockClosedPositionRepository $stockClosedPositionRepository,
		private StockAssetDividendRecordRepository $stockAssetDividendRecordRepository,
		private TaxCurrencyConverter $currencyConverter,
		private TaxYearRulesProvider $rulesProvider,
		private TaxTreatyRateProvider $treatyRateProvider,
		private BusinessIncomeCalculator $businessIncomeCalculator,
		private HealthInsuranceCalculator $healthInsuranceCalculator,
		private SocialInsuranceCalculator $socialInsuranceCalculator,
		private StockSalesCalculator $stockSalesCalculator,
		private DividendTaxCalculator $dividendTaxCalculator,
		private IncomeTaxCalculator $incomeTaxCalculator,
	)
	{
	}

	public function build(TaxYearReport $report): TaxYearReportSnapshotDTO
	{
		$appAdmin = $report->getAppAdmin();
		$year = $report->getYear();
		$asOfDate = $report->getAsOfDate();

		$profile = $this->profileFacade->getOrCreate($year, $appAdmin);
		$yearRules = $this->rulesProvider->getForYear($year);
		$nextYearRules = $this->rulesProvider->getForYear($year + 1);

		$isOpenYear = !$profile->isClosed() && $asOfDate->getYear() <= $year;
		$warnings = [];

		if ($yearRules->isEstimated) {
			$warnings[] = sprintf(
				'Pro rok %d nejsou k dispozici ověřené zákonné parametry — výpočet používá odhadované hodnoty.',
				$year,
			);
		}

		$businessIncome = $this->buildBusinessIncomeSection(
			$appAdmin,
			$profile,
			$year,
			$asOfDate,
			$isOpenYear,
			$warnings,
		);

		$stockSales = $this->buildStockSalesSection($appAdmin, $year, $asOfDate, $isOpenYear, $yearRules, $warnings);
		$dividendInputs = $this->buildDividendInputs($appAdmin, $year, $asOfDate, $isOpenYear, $warnings);
		$initialDividends = $this->dividendTaxCalculator->calculate(
			$dividendInputs,
			$profile->getDividendMode(),
		);

		$paymentsData = $this->buildPaymentsAndBankDonations(
			$appAdmin,
			$year,
			$asOfDate,
			$isOpenYear,
			$warnings,
		);

		$donations = $this->mergeDonations($profile, $paymentsData['bankDonations']);
		$rawDonationsCzk = 0;
		foreach ($donations as $donation) {
			$rawDonationsCzk += $donation->amountCzk;
		}

		$previousReport = $this->reportRepository->findLatestCompletedForYear($year - 1, $appAdmin);
		$previousSnapshot = $previousReport?->getSnapshot();

		$advances = $this->resolveAdvances(
			profile: $profile,
			year: $year,
			asOfDate: $asOfDate,
			isOpenYear: $isOpenYear,
			yearRules: $yearRules,
			paidByInstitution: $paymentsData['paidAdvancesByInstitution'],
			paidCountByInstitution: $paymentsData['paidAdvanceCountByInstitution'],
			maxPaidMonthByInstitution: $paymentsData['maxPaidAdvanceMonthByInstitution'],
			lastPaidByInstitution: $paymentsData['lastPaidAdvanceByInstitution'],
			previousSnapshot: $previousSnapshot,
			warnings: $warnings,
		);

		$healthResult = $this->healthInsuranceCalculator->calculate(
			section7TaxBaseCzk: $businessIncome->taxBaseCzk,
			activeMonths: $profile->getActiveMonthsHealth(),
			yearRules: $yearRules,
			nextYearRules: $nextYearRules,
			paidAdvancesCzk: $advances['health']['paid'],
			expectedRemainingAdvancesCzk: $advances['health']['expectedRemaining'],
			advancesSourceNote: $advances['health']['sourceNote'],
		);

		$socialResult = $this->socialInsuranceCalculator->calculate(
			section7TaxBaseCzk: $businessIncome->taxBaseCzk,
			activeMonths: $profile->getActiveMonthsSocial(),
			yearRules: $yearRules,
			nextYearRules: $nextYearRules,
			paidAdvancesCzk: $advances['social']['paid'],
			expectedRemainingAdvancesCzk: $advances['social']['expectedRemaining'],
			sicknessInsurancePaidCzk: $paymentsData['sicknessInsurancePaidCzk'],
			advancesSourceNote: $advances['social']['sourceNote'],
		);

		$incomeTaxResult = $this->incomeTaxCalculator->calculate(
			section7TaxBaseCzk: $businessIncome->taxBaseCzk,
			section10TaxBaseCzk: $stockSales->taxBaseSection10Czk,
			dividends: $initialDividends,
			selectedDividendMode: $profile->getDividendMode(),
			rawDonationsCzk: $rawDonationsCzk,
			pensionAndLifeDeductionCzk: $profile->getPensionAndLifeDeductionCzk(),
			mortgageInterestDeductionCzk: $profile->getMortgageInterestDeductionCzk(),
			otherDeductionsCzk: $profile->getOtherDeductionsCzk(),
			otherTaxCreditsCzk: $profile->getOtherTaxCreditsCzk(),
			rules: $yearRules,
			paidAdvancesCzk: $advances['taxOffice']['paid'],
			expectedRemainingAdvancesCzk: $advances['taxOffice']['expectedRemaining'],
			advancesSourceNote: $advances['taxOffice']['sourceNote'],
		);

		if ($incomeTaxResult['detail']->donationsBelowMinimum) {
			$warnings[] = sprintf(
				'Zadané dary (%s Kč) nedosahují zákonného minima pro odpočet (alespoň 1 000 Kč nebo přes 2 %% základu daně).',
				number_format($rawDonationsCzk, 0, ',', ' '),
			);
		}

		if ($incomeTaxResult['detail']->donationsCappedAtMax) {
			$warnings[] = sprintf(
				'Část darů přesahuje zákonný strop %d %% základu daně a nebyla v plné výši uplatněna.',
				(int) round($yearRules->giftMaxBasePercentage * 100),
			);
		}

		if ($stockSales->exceededTimeTestCap) {
			$warnings[] = sprintf(
				'Příjmy z akcií osvobozené časovým testem přesahují roční strop %s Kč pro rok %d — výsledek § 10 je předběžný.',
				number_format($stockSales->timeTestExemptionCapCzk ?? 0, 0, ',', ' '),
				$year,
			);
		}

		if ($stockSales->notification38vItemCount > 0) {
			$warnings[] = sprintf(
				'Byl zachycen osvobozený příjem z prodeje akcií nad 5 000 000 Kč (%d×) — prověřte oznamovací povinnost podle § 38v.',
				$stockSales->notification38vItemCount,
			);
		}

		if ($businessIncome->useCustomForecast
			&& $businessIncome->customForecastAnnualIncomeCzk !== null
			&& $businessIncome->customForecastAnnualIncomeCzk < $businessIncome->actualReceivedIncomeCzk
		) {
			$warnings[] = sprintf(
				'Vlastní odhad pracovního příjmu (%s Kč) je nižší než dosud potvrzené příjmy (%s Kč).',
				number_format($businessIncome->customForecastAnnualIncomeCzk, 0, ',', ' '),
				number_format($businessIncome->actualReceivedIncomeCzk, 0, ',', ' '),
			);
		}

		$taxOfficeSummary = $incomeTaxResult['summary'];
		$healthSummary = $healthResult['summary'];
		$socialSummary = $socialResult['summary'];

		$totalAnnualLiabilityCzk = $taxOfficeSummary->annualLiabilityCzk
			+ $healthSummary->annualLiabilityCzk
			+ $socialSummary->annualLiabilityCzk;
		$totalPaidAdvancesCzk = $taxOfficeSummary->paidAdvancesCzk
			+ $healthSummary->paidAdvancesCzk
			+ $socialSummary->paidAdvancesCzk;
		$totalExpectedRemainingAdvancesCzk = $taxOfficeSummary->expectedRemainingAdvancesCzk
			+ $healthSummary->expectedRemainingAdvancesCzk
			+ $socialSummary->expectedRemainingAdvancesCzk;
		$totalReserveToPrepareCzk = $taxOfficeSummary->payableShortfallCzk
			+ $healthSummary->payableShortfallCzk
			+ $socialSummary->payableShortfallCzk;
		$totalExpectedOverpaymentsCzk = $taxOfficeSummary->expectedOverpaymentCzk
			+ $healthSummary->expectedOverpaymentCzk
			+ $socialSummary->expectedOverpaymentCzk;

		return new TaxYearReportSnapshotDTO(
			year: $year,
			asOfDate: $asOfDate->format('Y-m-d'),
			isOpenYear: $isOpenYear,
			isClosed: $profile->isClosed(),
			isEstimatedRules: $yearRules->isEstimated,
			reserveTargetDate: $profile->getReserveTargetDate(),
			businessIncome: $businessIncome,
			stockSales: $stockSales,
			dividends: $incomeTaxResult['dividends'],
			healthInsuranceDetail: $healthResult['detail'],
			socialInsuranceDetail: $socialResult['detail'],
			incomeTaxDetail: $incomeTaxResult['detail'],
			taxOfficeSummary: $taxOfficeSummary,
			healthInsuranceSummary: $healthSummary,
			socialInsuranceSummary: $socialSummary,
			totalAnnualLiabilityCzk: $totalAnnualLiabilityCzk,
			totalPaidAdvancesCzk: $totalPaidAdvancesCzk,
			totalExpectedRemainingAdvancesCzk: $totalExpectedRemainingAdvancesCzk,
			totalReserveToPrepareCzk: $totalReserveToPrepareCzk,
			totalExpectedOverpaymentsCzk: $totalExpectedOverpaymentsCzk,
			totalRemainingCashflowThisYearAndSettlementCzk: $totalExpectedRemainingAdvancesCzk + $totalReserveToPrepareCzk,
			donations: $donations,
			payments: $paymentsData['payments'],
			warnings: array_values(array_unique($warnings)),
		);
	}

	/**
	 * @param list<string> $warnings
	 */
	private function buildBusinessIncomeSection(
		AppAdmin $appAdmin,
		TaxYearProfile $profile,
		int $year,
		ImmutableDateTime $asOfDate,
		bool $isOpenYear,
		array &$warnings,
	): TaxBusinessIncomeSectionDTO
	{
		$currentYearRecords = $this->indexWorkIncomeByMonth(
			$this->workMonthlyIncomeRepository->findAllForAppAdmin($appAdmin, $year),
		);
		$previousYearRecords = $this->indexWorkIncomeByMonth(
			$this->workMonthlyIncomeRepository->findAllForAppAdmin($appAdmin, $year - 1),
		);

		$asOfYear = $asOfDate->getYear();
		$asOfMonth = $asOfDate->getMonth();

		// Determine average monthly income for open-year forecast
		$forecastAverage = $this->resolveMonthlyWorkIncomeAverage(
			$year,
			$asOfYear,
			$asOfMonth,
			$currentYearRecords,
			$previousYearRecords,
		);

		$items = [];

		// 1. Previous year December (paid in January of $year)
		if ($profile->isIncludePreviousDecemberWorkIncome()) {
			$prevDec = $previousYearRecords[12] ?? null;
			if ($prevDec !== null) {
				if ($prevDec->getHours() <= 0.0) {
					$warnings[] = sprintf(
						'Záznam WorkMonthlyIncome pro %s %d má 0 hodin — ověřte, že nejde o nevyplněný měsíc.',
						DatetimeConst::CZECH_MONTHS[12],
						$year - 1,
					);
				}

				$items[] = new TaxWorkIncomeMonthItemDTO(
					workYear: $year - 1,
					workMonth: 12,
					label: sprintf('%s %d (inkaso leden %d)', DatetimeConst::CZECH_MONTHS[12], $year - 1, $year),
					hours: $prevDec->getHours(),
					hourlyRateCzk: $prevDec->getHourlyRate(),
					amountCzk: TaxMath::roundCzk($prevDec->getHours() * $prevDec->getHourlyRate()),
					isEstimated: false,
					isPreviousYearDecember: true,
					isMissing: false,
				);
			} else {
				$warnings[] = sprintf(
					'Chybí záznam pracovní fakturace za prosinec %d (obvykle inkasovaný v lednu %d).',
					$year - 1,
					$year,
				);
				$items[] = new TaxWorkIncomeMonthItemDTO(
					workYear: $year - 1,
					workMonth: 12,
					label: sprintf('%s %d (inkaso leden %d)', DatetimeConst::CZECH_MONTHS[12], $year - 1, $year),
					hours: 0.0,
					hourlyRateCzk: 0,
					amountCzk: 0,
					isEstimated: false,
					isPreviousYearDecember: true,
					isMissing: true,
				);
			}
		}

		// 2. Months of $year: January..November (or December too if cash-basis shift is disabled)
		$lastWorkMonthForYear = $profile->isIncludePreviousDecemberWorkIncome() ? 11 : 12;

		for ($month = 1; $month <= $lastWorkMonthForYear; $month++) {
			$record = $currentYearRecords[$month] ?? null;
			$isFutureOrCurrentMonth = $isOpenYear && ($asOfYear < $year || ($asOfYear === $year && $month >= $asOfMonth));

			if (!$isFutureOrCurrentMonth) {
				if ($record !== null) {
					if ($record->getHours() <= 0.0) {
						$warnings[] = sprintf(
							'Záznam WorkMonthlyIncome pro %s %d má 0 hodin — ověřte, že nejde o nevyplněný měsíc.',
							DatetimeConst::CZECH_MONTHS[$month],
							$year,
						);
					}

					$items[] = new TaxWorkIncomeMonthItemDTO(
						workYear: $year,
						workMonth: $month,
						label: sprintf('%s %d', DatetimeConst::CZECH_MONTHS[$month], $year),
						hours: $record->getHours(),
						hourlyRateCzk: $record->getHourlyRate(),
						amountCzk: TaxMath::roundCzk($record->getHours() * $record->getHourlyRate()),
						isEstimated: false,
						isPreviousYearDecember: false,
						isMissing: false,
					);
				} else {
					$warnings[] = sprintf(
						'Chybí záznam WorkMonthlyIncome pro %s %d.',
						DatetimeConst::CZECH_MONTHS[$month],
						$year,
					);
					$items[] = new TaxWorkIncomeMonthItemDTO(
						workYear: $year,
						workMonth: $month,
						label: sprintf('%s %d', DatetimeConst::CZECH_MONTHS[$month], $year),
						hours: 0.0,
						hourlyRateCzk: 0,
						amountCzk: 0,
						isEstimated: false,
						isPreviousYearDecember: false,
						isMissing: true,
					);
				}
			} else {
				// Current or future month in an open year: use known amount if higher than forecast, else forecast
				$knownAmount = $record !== null
					? TaxMath::roundCzk($record->getHours() * $record->getHourlyRate())
					: 0;
				$estimatedAmount = max($knownAmount, $forecastAverage['averageMonthlyCzk']);
				$isCurrentMonthWithPartialData = $asOfYear === $year && $month === $asOfMonth && $knownAmount > 0;

				$items[] = new TaxWorkIncomeMonthItemDTO(
					workYear: $year,
					workMonth: $month,
					label: sprintf(
						'%s %d (%s)',
						DatetimeConst::CZECH_MONTHS[$month],
						$year,
						$isCurrentMonthWithPartialData ? 'rozpracovaný + odhad' : 'odhad',
					),
					hours: $record?->getHours() ?? 0.0,
					hourlyRateCzk: $record?->getHourlyRate() ?? 0,
					amountCzk: $estimatedAmount,
					isEstimated: true,
					isPreviousYearDecember: false,
					isMissing: false,
				);
			}
		}

		return $this->businessIncomeCalculator->calculate(
			items: $items,
			flatRate: $profile->getExpenseFlatRate(),
			manualAdjustmentCzk: $profile->getManualBusinessIncomeAdjustmentCzk(),
			manualAdjustmentNote: $profile->getManualBusinessIncomeAdjustmentNote(),
			autoForecastPeriodDescription: $forecastAverage['description'],
			autoForecastMonthsUsed: $forecastAverage['monthsUsed'],
			customForecastAnnualIncomeCzk: $profile->getCustomForecastAnnualIncomeCzk(),
			useCustomForecast: $profile->isUseCustomForecast(),
			isOpenYear: $isOpenYear,
		);
	}

	/**
	 * @param array<int, WorkMonthlyIncome> $currentYearRecords
	 * @param array<int, WorkMonthlyIncome> $previousYearRecords
	 * @return array{averageMonthlyCzk: int, monthsUsed: int, description: string}
	 */
	private function resolveMonthlyWorkIncomeAverage(
		int $year,
		int $asOfYear,
		int $asOfMonth,
		array $currentYearRecords,
		array $previousYearRecords,
	): array
	{
		$completedCurrentYearAmounts = [];
		if ($asOfYear === $year) {
			for ($month = 1; $month < $asOfMonth; $month++) {
				if (isset($currentYearRecords[$month])) {
					$rec = $currentYearRecords[$month];
					$amount = TaxMath::roundCzk($rec->getHours() * $rec->getHourlyRate());
					if ($amount > 0) {
						$completedCurrentYearAmounts[] = $amount;
					}
				}
			}
		}

		if ($completedCurrentYearAmounts !== []) {
			$count = count($completedCurrentYearAmounts);

			return [
				'averageMonthlyCzk' => TaxMath::roundCzk(array_sum($completedCurrentYearAmounts) / $count),
				'monthsUsed' => $count,
				'description' => sprintf('Průměr z %d dokončených měsíců roku %d', $count, $year),
			];
		}

		$previousYearAmounts = [];
		foreach ($previousYearRecords as $rec) {
			$amount = TaxMath::roundCzk($rec->getHours() * $rec->getHourlyRate());
			if ($amount > 0) {
				$previousYearAmounts[] = $amount;
			}
		}

		if ($previousYearAmounts !== []) {
			$count = count($previousYearAmounts);

			return [
				'averageMonthlyCzk' => TaxMath::roundCzk(array_sum($previousYearAmounts) / $count),
				'monthsUsed' => $count,
				'description' => sprintf('Průměr z %d měsíců předchozího roku %d', $count, $year - 1),
			];
		}

		return [
			'averageMonthlyCzk' => 0,
			'monthsUsed' => 0,
			'description' => 'Bez historických dat pro odhad',
		];
	}

	/**
	 * @param array<WorkMonthlyIncome> $records
	 * @return array<int, WorkMonthlyIncome>
	 */
	private function indexWorkIncomeByMonth(array $records): array
	{
		$indexed = [];
		foreach ($records as $record) {
			$indexed[$record->getMonth()] = $record;
		}

		return $indexed;
	}

	/**
	 * @param list<string> $warnings
	 */
	private function buildStockSalesSection(
		AppAdmin $appAdmin,
		int $year,
		ImmutableDateTime $asOfDate,
		bool $isOpenYear,
		TaxYearRules $yearRules,
		array &$warnings,
	): TaxStockSalesSectionDTO
	{
		$start = new ImmutableDateTime(sprintf('%d-01-01 00:00:00', $year));
		$endOfYear = new ImmutableDateTime(sprintf('%d-12-31 23:59:59', $year));
		$end = $isOpenYear && $asOfDate < $endOfYear ? $asOfDate : $endOfYear;

		$closedPositions = $this->stockClosedPositionRepository->findBetweenDatesForAppAdmin($appAdmin, $start, $end);
		$saleInputs = [];
		$missingRateCount = 0;

		foreach ($closedPositions as $closedPosition) {
			$position = $closedPosition->getAssetPositon();
			assert($position instanceof StockPosition);
			$asset = $position->getAsset();
			assert($asset instanceof StockAsset);

			$buyPrice = $position->isDifferentBrokerAmount()
				? $position->getTotalInvestedAmountInBrokerCurrency()
				: $position->getTotalInvestedAmount();
			$sellPrice = $closedPosition->isDifferentBrokerAmount()
				? $closedPosition->getTotalCloseAmountInBrokerCurrency()
				: $closedPosition->getCloseTotalAmount();

			$buyConverted = $this->currencyConverter->convertToCzk(
				$buyPrice->getPrice(),
				$buyPrice->getCurrency(),
				$position->getOrderDate(),
			);
			$sellConverted = $this->currencyConverter->convertToCzk(
				$sellPrice->getPrice(),
				$sellPrice->getCurrency(),
				$closedPosition->getDate(),
			);

			$hasMissingRate = $buyConverted['missingRate'] || $sellConverted['missingRate'];
			if ($hasMissingRate) {
				$missingRateCount++;
			}

			$saleInputs[] = new TaxStockSaleInput(
				closedPositionId: $closedPosition->getId()->toString(),
				assetName: $asset->getName(),
				ticker: $asset->getTicker(),
				pieces: $position->getOrderPiecesCount(),
				buyDate: $position->getOrderDate(),
				sellDate: $closedPosition->getDate(),
				buyCurrency: $buyPrice->getCurrency()->value,
				buyAmountOriginal: $buyPrice->getPrice(),
				buyExchangeRate: $buyConverted['rate'],
				buyExchangeRateDate: $buyConverted['rateDate'],
				buyAmountCzk: $buyConverted['amountCzk'],
				sellCurrency: $sellPrice->getCurrency()->value,
				sellAmountOriginal: $sellPrice->getPrice(),
				sellExchangeRate: $sellConverted['rate'],
				sellExchangeRateDate: $sellConverted['rateDate'],
				sellAmountCzk: $sellConverted['amountCzk'],
				hasMissingExchangeRate: $hasMissingRate,
			);
		}

		if ($missingRateCount > 0) {
			$warnings[] = sprintf(
				'U %d uzavřených akciových pozic chyběl historický kurz ČNB pro nákup nebo prodej.',
				$missingRateCount,
			);
		}

		return $this->stockSalesCalculator->calculate($saleInputs, $yearRules);
	}

	/**
	 * @param list<string> $warnings
	 * @return list<TaxDividendInput>
	 */
	private function buildDividendInputs(
		AppAdmin $appAdmin,
		int $year,
		ImmutableDateTime $asOfDate,
		bool $isOpenYear,
		array &$warnings,
	): array
	{
		$start = new ImmutableDateTime(sprintf('%d-01-01 00:00:00', $year));
		$endOfYear = new ImmutableDateTime(sprintf('%d-12-31 23:59:59', $year));
		$end = $isOpenYear && $asOfDate < $endOfYear ? $asOfDate : $endOfYear;

		$records = $this->stockAssetDividendRecordRepository->findCashReceivedBetweenDates($start, $end, $appAdmin);
		$inputs = [];
		$missingRateCount = 0;
		$inferredCountryTickers = [];
		$missingWithholdingTickers = [];

		foreach ($records as $record) {
			$dividend = $record->getStockAssetDividend();
			$asset = $dividend->getStockAsset();
			$effectiveDate = $dividend->getPaymentDate() ?? $dividend->getExDate();

			$grossOriginal = $record->getTotalAmountInBrokerCurrency() !== null && $record->getBrokerCurrency() !== null
				? $record->getTotalAmountInBrokerCurrency()
				: $record->getTotalAmount();
			$currency = $record->getTotalAmountInBrokerCurrency() !== null && $record->getBrokerCurrency() !== null
				? $record->getBrokerCurrency()
				: $record->getCurrency();

			$withheldRatePercentage = $record->getDividendTax() ?? 0.0;
			if ($record->getDividendTax() === null) {
				$missingWithholdingTickers[] = $asset->getTicker();
			}

			$withheldOriginal = $grossOriginal * $withheldRatePercentage / 100;

			$grossConverted = $this->currencyConverter->convertToCzk($grossOriginal, $currency, $effectiveDate);
			$withheldConverted = $this->currencyConverter->convertToCzk($withheldOriginal, $currency, $effectiveDate);

			if ($grossConverted['missingRate']) {
				$missingRateCount++;
			}

			$countryCode = $this->treatyRateProvider->resolveCountryCodeFromStockAsset($asset);
			$inferredWithoutIsin = $this->treatyRateProvider->isCountryInferredWithoutIsin($asset);
			if ($inferredWithoutIsin) {
				$inferredCountryTickers[] = $asset->getTicker();
			}

			$inputs[] = new TaxDividendInput(
				dividendRecordId: $record->getId()->toString(),
				assetName: $asset->getName(),
				ticker: $asset->getTicker(),
				countryCode: $countryCode,
				paymentDate: $effectiveDate->format('Y-m-d'),
				currency: $currency->value,
				grossAmountOriginal: $grossOriginal,
				withheldTaxRatePercentage: $withheldRatePercentage,
				withheldTaxOriginal: $withheldOriginal,
				exchangeRate: $grossConverted['rate'],
				exchangeRateDate: $grossConverted['rateDate'],
				grossCzk: $grossConverted['amountCzk'],
				withheldTaxCzk: $withheldConverted['amountCzk'],
				countryInferredWithoutIsin: $inferredWithoutIsin,
				hasMissingExchangeRate: $grossConverted['missingRate'],
			);
		}

		if ($missingRateCount > 0) {
			$warnings[] = sprintf(
				'U %d vyplacených dividend chyběl historický kurz ČNB ke dni výplaty.',
				$missingRateCount,
			);
		}

		if ($inferredCountryTickers !== []) {
			$unique = array_values(array_unique($inferredCountryTickers));
			$warnings[] = sprintf(
				'U dividend bez vyplněného ISIN (%s) byl stát emitenta odvozen pouze z burzy — ověřte proti brokerskému výpisu.',
				implode(', ', $unique),
			);
		}

		if ($missingWithholdingTickers !== []) {
			$unique = array_values(array_unique($missingWithholdingTickers));
			$warnings[] = sprintf(
				'U dividend (%s) není na akcii nastavena sazba srážkové daně — započítáno 0 %% srážky.',
				implode(', ', $unique),
			);
		}

		return $inputs;
	}

	/**
	 * @param list<string> $warnings
	 * @return array{
	 *     payments: list<TaxPaymentItemDTO>,
	 *     paidAdvancesByInstitution: array<string, int>,
	 *     paidAdvanceCountByInstitution: array<string, int>,
	 *     maxPaidAdvanceMonthByInstitution: array<string, int>,
	 *     lastPaidAdvanceByInstitution: array<string, int>,
	 *     sicknessInsurancePaidCzk: int,
	 *     bankDonations: list<TaxDonationItemDTO>
	 * }
	 */
	private function buildPaymentsAndBankDonations(
		AppAdmin $appAdmin,
		int $year,
		ImmutableDateTime $asOfDate,
		bool $isOpenYear,
		array &$warnings,
	): array
	{
		$expenses = $this->bankExpenseRepository->findTaxRelevantForAppAdmin($appAdmin, $year);
		$seenIds = [];
		$payments = [];
		$bankDonations = [];
		$paidAdvancesByInstitution = [
			TaxInstitutionEnum::TAX_OFFICE->value => 0,
			TaxInstitutionEnum::HEALTH_INSURANCE->value => 0,
			TaxInstitutionEnum::SOCIAL_INSURANCE->value => 0,
		];
		$paidAdvanceCountByInstitution = [
			TaxInstitutionEnum::TAX_OFFICE->value => 0,
			TaxInstitutionEnum::HEALTH_INSURANCE->value => 0,
			TaxInstitutionEnum::SOCIAL_INSURANCE->value => 0,
		];
		$maxPaidAdvanceMonthByInstitution = [
			TaxInstitutionEnum::TAX_OFFICE->value => 0,
			TaxInstitutionEnum::HEALTH_INSURANCE->value => 0,
			TaxInstitutionEnum::SOCIAL_INSURANCE->value => 0,
		];
		$lastPaidAdvanceByInstitution = [];
		/** @var array<string, ImmutableDateTime> $lastPaidAdvanceDateByInstitution */
		$lastPaidAdvanceDateByInstitution = [];
		$sicknessInsurancePaidCzk = 0;

		foreach ($expenses as $expense) {
			$expenseId = $expense->getId()->toString();
			if (isset($seenIds[$expenseId])) {
				continue;
			}

			$seenIds[$expenseId] = true;
			$role = $expense->resolveTaxPaymentRole();
			if ($role === null) {
				continue;
			}

			$effectiveDate = $expense->getDate();
			if ($isOpenYear && $effectiveDate > $asOfDate) {
				continue;
			}

			$assignedYear = $this->resolveAssignedTaxYear($expense, $role);
			if ($assignedYear !== $year) {
				continue;
			}

			$amountAbsOriginal = abs($expense->getAmount());
			if ($expense->getCurrency() === CurrencyEnum::CZK) {
				$amountCzk = TaxMath::roundCzk($amountAbsOriginal);
			} else {
				$converted = $this->currencyConverter->convertToCzk(
					$amountAbsOriginal,
					$expense->getCurrency(),
					$effectiveDate,
				);
				if ($converted['missingRate']) {
					$warnings[] = sprintf(
						'U daňové platby z %s (%s) chyběl kurz ČNB pro převod do CZK.',
						$effectiveDate->format('j. n. Y'),
						$expense->getCurrency()->value,
					);
				}

				$amountCzk = $converted['amountCzk'];
			}

			$description = $this->buildExpenseShortDescription($expense);

			if ($role->isDonation()) {
				$bankDonations[] = new TaxDonationItemDTO(
					source: 'bank_expense',
					id: $expenseId,
					recipientName: $description,
					amountCzk: $amountCzk,
					donatedAt: $effectiveDate->format('Y-m-d'),
					note: 'Z bankovního výdaje (tag Dar)',
				);
			}

			$institution = $role->getInstitution();
			$countedAsAdvance = $role->isCountedAsAdvance();

			if ($countedAsAdvance && $institution !== null) {
				$paidAdvancesByInstitution[$institution->value] += $amountCzk;
				$paidAdvanceCountByInstitution[$institution->value]++;
				$coveredMonth = $this->resolveCoveredAdvanceMonth($institution, $effectiveDate, $year);
				if ($coveredMonth > 0) {
					$maxPaidAdvanceMonthByInstitution[$institution->value] = max(
						$maxPaidAdvanceMonthByInstitution[$institution->value],
						$coveredMonth,
					);
				}

				$lastDate = $lastPaidAdvanceDateByInstitution[$institution->value] ?? null;
				if ($lastDate === null || $effectiveDate >= $lastDate) {
					$lastPaidAdvanceByInstitution[$institution->value] = $amountCzk;
					$lastPaidAdvanceDateByInstitution[$institution->value] = $effectiveDate;
				}
			} elseif ($role === TaxPaymentRoleEnum::SICKNESS_INSURANCE) {
				$sicknessInsurancePaidCzk += $amountCzk;
			}

			$payments[] = new TaxPaymentItemDTO(
				bankExpenseId: $expenseId,
				date: $effectiveDate->format('Y-m-d'),
				amountCzk: $amountCzk,
				roleCode: $role->value,
				roleLabel: $role->format(),
				institutionCode: $institution?->value,
				assignedTaxYear: $assignedYear,
				isYearOverridden: $expense->getTaxYearOverride() !== null,
				isRoleOverridden: $expense->getTaxPaymentRoleOverride() !== null,
				countedAsAdvance: $countedAsAdvance,
				description: $description,
			);
		}

		return [
			'payments' => $payments,
			'paidAdvancesByInstitution' => $paidAdvancesByInstitution,
			'paidAdvanceCountByInstitution' => $paidAdvanceCountByInstitution,
			'maxPaidAdvanceMonthByInstitution' => $maxPaidAdvanceMonthByInstitution,
			'lastPaidAdvanceByInstitution' => $lastPaidAdvanceByInstitution,
			'sicknessInsurancePaidCzk' => $sicknessInsurancePaidCzk,
			'bankDonations' => $bankDonations,
		];
	}

	private function resolveAssignedTaxYear(BankExpense $expense, TaxPaymentRoleEnum $role): int
	{
		if ($expense->getTaxYearOverride() !== null) {
			return $expense->getTaxYearOverride();
		}

		$date = $expense->getDate();
		$calendarYear = $date->getYear();

		// December health insurance advance is due by January 8th of the following year.
		if (
			$role === TaxPaymentRoleEnum::HEALTH_INSURANCE_ADVANCE
			&& $date->getMonth() === 1
			&& $date->getDay() <= 10
		) {
			return $calendarYear - 1;
		}

		return $calendarYear;
	}

	private function buildExpenseShortDescription(BankExpense $expense): string
	{
		$tagName = $expense->getMainTag()?->getName();
		$raw = trim((string) preg_replace('/\s+/', ' ', $expense->getTransactionRawContent()));
		if (mb_strlen($raw) > 80) {
			$raw = mb_substr($raw, 0, 77) . '...';
		}

		if ($tagName !== null && $raw !== '') {
			return sprintf('%s – %s', $tagName, $raw);
		}

		return $tagName ?? ($raw !== '' ? $raw : 'Bankovní platba');
	}

	/**
	 * @param list<TaxDonationItemDTO> $bankDonations
	 * @return list<TaxDonationItemDTO>
	 */
	private function mergeDonations(TaxYearProfile $profile, array $bankDonations): array
	{
		$donations = [];
		foreach ($this->donationRepository->findByProfile($profile) as $donation) {
			$donations[] = new TaxDonationItemDTO(
				source: 'manual',
				id: $donation->getId()->toString(),
				recipientName: $donation->getRecipientName(),
				amountCzk: $donation->getAmountCzk(),
				donatedAt: $donation->getDonatedAt()?->format('Y-m-d'),
				note: $donation->getNote(),
			);
		}

		foreach ($bankDonations as $bankDonation) {
			$donations[] = $bankDonation;
		}

		return $donations;
	}

	/**
	 * @param array<string, int> $paidByInstitution
	 * @param array<string, int> $paidCountByInstitution
	 * @param array<string, int> $maxPaidMonthByInstitution
	 * @param array<string, int> $lastPaidByInstitution
	 * @param list<string> $warnings
	 * @return array{
	 *     taxOffice: array{paid: int, expectedRemaining: int, sourceNote: string},
	 *     health: array{paid: int, expectedRemaining: int, sourceNote: string},
	 *     social: array{paid: int, expectedRemaining: int, sourceNote: string}
	 * }
	 */
	private function resolveAdvances(
		TaxYearProfile $profile,
		int $year,
		ImmutableDateTime $asOfDate,
		bool $isOpenYear,
		TaxYearRules $yearRules,
		array $paidByInstitution,
		array $paidCountByInstitution,
		array $maxPaidMonthByInstitution,
		array $lastPaidByInstitution,
		TaxYearReportSnapshotDTO|null $previousSnapshot,
		array &$warnings,
	): array
	{
		$healthRemainingMonths = $isOpenYear
			? $this->calculateRemainingAdvanceMonthsForInstitution(
				$year,
				$asOfDate,
				$paidCountByInstitution[TaxInstitutionEnum::HEALTH_INSURANCE->value],
				$maxPaidMonthByInstitution[TaxInstitutionEnum::HEALTH_INSURANCE->value],
			)
			: 0;
		$socialRemainingMonths = $isOpenYear
			? $this->calculateRemainingAdvanceMonthsForInstitution(
				$year,
				$asOfDate,
				$paidCountByInstitution[TaxInstitutionEnum::SOCIAL_INSURANCE->value],
				$maxPaidMonthByInstitution[TaxInstitutionEnum::SOCIAL_INSURANCE->value],
			)
			: 0;

		$usedPreviousSnapshotForAdvances = false;

		// Health insurance advances
		$healthPaid = $profile->getConfirmedHealthAdvancesCzk()
			?? $paidByInstitution[TaxInstitutionEnum::HEALTH_INSURANCE->value];
		$healthSourceNote = $profile->getConfirmedHealthAdvancesCzk() !== null
			? 'Potvrzený roční součet záloh v nastavení roku'
			: 'Z bankovních výdajů podle daňové role';
		$healthRemaining = 0;

		if ($isOpenYear && $profile->getConfirmedHealthAdvancesCzk() === null && $healthRemainingMonths > 0) {
			if ($profile->getExpectedMonthlyHealthAdvanceCzk() !== null) {
				$monthly = $profile->getExpectedMonthlyHealthAdvanceCzk();
				$healthRemaining = $healthRemainingMonths * $monthly;
				$healthSourceNote = sprintf(
					'Ruční měsíční záloha %s Kč × %d zbývajících měsíců',
					number_format($monthly, 0, ',', ' '),
					$healthRemainingMonths,
				);
			} elseif (isset($lastPaidByInstitution[TaxInstitutionEnum::HEALTH_INSURANCE->value])) {
				$monthly = max(
					$lastPaidByInstitution[TaxInstitutionEnum::HEALTH_INSURANCE->value],
					$yearRules->healthMinMonthlyAdvanceCzk,
				);
				$healthRemaining = $healthRemainingMonths * $monthly;
				$healthSourceNote = sprintf(
					'Podle poslední zaplacené zálohy (%s Kč × %d zbývajících měsíců)',
					number_format($monthly, 0, ',', ' '),
					$healthRemainingMonths,
				);
			} elseif (
				$previousSnapshot !== null
				&& $previousSnapshot->healthInsuranceDetail->nextMonthlyAdvanceCzk > 0
			) {
				$usedPreviousSnapshotForAdvances = true;
				$monthly = max(
					$previousSnapshot->healthInsuranceDetail->nextMonthlyAdvanceCzk,
					$yearRules->healthMinMonthlyAdvanceCzk,
				);
				$healthRemaining = $healthRemainingMonths * $monthly;
				$healthSourceNote = sprintf(
					'Z přehledu za rok %d (%s Kč × %d měsíců)',
					$year - 1,
					number_format($monthly, 0, ',', ' '),
					$healthRemainingMonths,
				);
			} else {
				$monthly = $yearRules->healthMinMonthlyAdvanceCzk;
				$healthRemaining = $healthRemainingMonths * $monthly;
				$healthSourceNote = sprintf(
					'Výchozí zákonné minimum (%s Kč × %d měsíců)',
					number_format($monthly, 0, ',', ' '),
					$healthRemainingMonths,
				);
				$warnings[] = 'Pro zdravotní pojištění nebyl nalezen předchozí přehled ani zaplacená záloha'
					. ' — pro zbývající měsíce použito zákonné minimum.';
			}
		}

		// Social insurance advances
		$socialPaid = $profile->getConfirmedSocialAdvancesCzk()
			?? $paidByInstitution[TaxInstitutionEnum::SOCIAL_INSURANCE->value];
		$socialSourceNote = $profile->getConfirmedSocialAdvancesCzk() !== null
			? 'Potvrzený roční součet záloh v nastavení roku'
			: 'Z bankovních výdajů podle daňové role';
		$socialRemaining = 0;

		if ($isOpenYear && $profile->getConfirmedSocialAdvancesCzk() === null && $socialRemainingMonths > 0) {
			if ($profile->getExpectedMonthlySocialAdvanceCzk() !== null) {
				$monthly = $profile->getExpectedMonthlySocialAdvanceCzk();
				$socialRemaining = $socialRemainingMonths * $monthly;
				$socialSourceNote = sprintf(
					'Ruční měsíční záloha %s Kč × %d zbývajících měsíců',
					number_format($monthly, 0, ',', ' '),
					$socialRemainingMonths,
				);
			} elseif (isset($lastPaidByInstitution[TaxInstitutionEnum::SOCIAL_INSURANCE->value])) {
				$lastPaid = $lastPaidByInstitution[TaxInstitutionEnum::SOCIAL_INSURANCE->value];
				$socialRemaining = 0;
				$startMonth = 12 - $socialRemainingMonths + 1;
				for ($m = $startMonth; $m <= 12; $m++) {
					$socialRemaining += max($lastPaid, $yearRules->getSocialMinMonthlyAdvanceForMonth($m));
				}

				$socialSourceNote = sprintf(
					'Podle poslední zaplacené zálohy (%s Kč × %d zbývajících měsíců)',
					number_format($lastPaid, 0, ',', ' '),
					$socialRemainingMonths,
				);
			} elseif (
				$previousSnapshot !== null
				&& $previousSnapshot->socialInsuranceDetail->nextMonthlyAdvanceCzk > 0
			) {
				$usedPreviousSnapshotForAdvances = true;
				$socialRemaining = 0;
				$startMonth = 12 - $socialRemainingMonths + 1;
				for ($m = $startMonth; $m <= 12; $m++) {
					$socialRemaining += max(
						$previousSnapshot->socialInsuranceDetail->nextMonthlyAdvanceCzk,
						$yearRules->getSocialMinMonthlyAdvanceForMonth($m),
					);
				}

				$socialSourceNote = sprintf(
					'Z přehledu za rok %d (%d zbývajících měsíců)',
					$year - 1,
					$socialRemainingMonths,
				);
			} else {
				$socialRemaining = 0;
				$startMonth = 12 - $socialRemainingMonths + 1;
				for ($m = $startMonth; $m <= 12; $m++) {
					$socialRemaining += $yearRules->getSocialMinMonthlyAdvanceForMonth($m);
				}

				$socialSourceNote = sprintf('Výchozí zákonné minimum (%d zbývajících měsíců)', $socialRemainingMonths);
				$warnings[] = 'Pro ČSSZ nebyl nalezen předchozí přehled ani zaplacená záloha — pro zbývající měsíce použito zákonné minimum.';
			}
		}

		// Income tax (FÚ) advances
		$taxOfficePaid = $profile->getConfirmedIncomeTaxAdvancesCzk()
			?? $paidByInstitution[TaxInstitutionEnum::TAX_OFFICE->value];
		$taxOfficeSourceNote = $profile->getConfirmedIncomeTaxAdvancesCzk() !== null
			? 'Potvrzený roční součet záloh v nastavení roku'
			: 'Z bankovních výdajů podle daňové role';
		$taxOfficeRemaining = 0;

		if ($isOpenYear && $profile->getConfirmedIncomeTaxAdvancesCzk() === null) {
			if ($profile->getExpectedRemainingIncomeTaxAdvancesCzk() !== null) {
				$taxOfficeRemaining = max(0, $profile->getExpectedRemainingIncomeTaxAdvancesCzk());
				$taxOfficeSourceNote = 'Ručně zadané zbývající zálohy DPFO v nastavení roku';
			} elseif ($previousSnapshot !== null && $previousSnapshot->incomeTaxDetail->nextAdvanceCountPerYear > 0) {
				$usedPreviousSnapshotForAdvances = true;
				$expectedAnnualCount = $previousSnapshot->incomeTaxDetail->nextAdvanceCountPerYear;
				$paidTaxAdvanceCount = $paidCountByInstitution[TaxInstitutionEnum::TAX_OFFICE->value];
				$calendarRemainingTerms = $this->countRemainingIncomeTaxAdvanceTerms(
					$year,
					$asOfDate,
					$expectedAnnualCount,
				);
				$remainingTerms = min($calendarRemainingTerms, max(0, $expectedAnnualCount - $paidTaxAdvanceCount));

				if ($remainingTerms > 0) {
					$advanceAmount = $lastPaidByInstitution[TaxInstitutionEnum::TAX_OFFICE->value]
						?? $previousSnapshot->incomeTaxDetail->nextAdvanceAmountCzk;
					$taxOfficeRemaining = $remainingTerms * $advanceAmount;
					$taxOfficeSourceNote = sprintf(
						'Z DAP za rok %d (%d zbývajících splátek × %s Kč)',
						$year - 1,
						$remainingTerms,
						number_format($advanceAmount, 0, ',', ' '),
					);
				} else {
					$taxOfficeSourceNote = sprintf(
						'Všechny předepsané zálohy DPFO (%d×) jsou již uhrazeny',
						$expectedAnnualCount,
					);
				}
			}
		}

		if ($usedPreviousSnapshotForAdvances && $previousSnapshot !== null && $previousSnapshot->isOpenYear) {
			$previousAsOf = DateTimeImmutable::createFromFormat('!Y-m-d', $previousSnapshot->asOfDate);
			$warnings[] = sprintf(
				'Očekávané zálohy vycházejí z neuzavřeného odhadu za rok %d (k %s) — po skončení roku %d ho přepočítejte nebo uzavřete.',
				$year - 1,
				$previousAsOf !== false ? $previousAsOf->format('j. n. Y') : $previousSnapshot->asOfDate,
				$year - 1,
			);
		}

		return [
			'taxOffice' => [
				'paid' => $taxOfficePaid,
				'expectedRemaining' => $taxOfficeRemaining,
				'sourceNote' => $taxOfficeSourceNote,
			],
			'health' => [
				'paid' => $healthPaid,
				'expectedRemaining' => $healthRemaining,
				'sourceNote' => $healthSourceNote,
			],
			'social' => [
				'paid' => $socialPaid,
				'expectedRemaining' => $socialRemaining,
				'sourceNote' => $socialSourceNote,
			],
		];
	}

	private function resolveCoveredAdvanceMonth(
		TaxInstitutionEnum $institution,
		ImmutableDateTime $effectiveDate,
		int $assignedYear,
	): int
	{
		if ($institution === TaxInstitutionEnum::HEALTH_INSURANCE) {
			if ($effectiveDate->getYear() === $assignedYear + 1) {
				return 12;
			}

			if ($effectiveDate->getYear() !== $assignedYear) {
				return 0;
			}

			// Health insurance paid by the 10th of month M (Feb..Dec) covers the preceding month M-1.
			if ($effectiveDate->getMonth() >= 2 && $effectiveDate->getDay() <= 10) {
				return $effectiveDate->getMonth() - 1;
			}

			return $effectiveDate->getMonth();
		}

		if ($effectiveDate->getYear() !== $assignedYear) {
			return 0;
		}

		return $effectiveDate->getMonth();
	}

	private function calculateRemainingAdvanceMonthsForInstitution(
		int $year,
		ImmutableDateTime $asOfDate,
		int $paidCount,
		int $maxCoveredMonth,
	): int
	{
		if ($asOfDate->getYear() < $year) {
			return 12;
		}

		if ($asOfDate->getYear() > $year) {
			return 0;
		}

		if ($paidCount >= 12) {
			return 0;
		}

		$asOfMonth = $asOfDate->getMonth();
		if ($maxCoveredMonth < $asOfMonth) {
			return max(0, 12 - $asOfMonth + 1);
		}

		return max(0, 12 - $asOfMonth);
	}

	private function countRemainingIncomeTaxAdvanceTerms(
		int $year,
		ImmutableDateTime $asOfDate,
		int $countPerYear,
	): int
	{
		$dueMonths = $countPerYear === 4 ? [3, 6, 9, 12] : ($countPerYear === 2 ? [6, 12] : []);
		$remaining = 0;

		foreach ($dueMonths as $month) {
			$dueDate = new ImmutableDateTime(sprintf('%d-%02d-15 23:59:59', $year, $month));
			if ($dueDate > $asOfDate) {
				$remaining++;
			}
		}

		return $remaining;
	}

}
