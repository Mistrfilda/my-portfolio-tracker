<?php

declare(strict_types = 1);

namespace App\Test\Unit\Tax;

use App\Tax\Calculator\BusinessIncomeCalculator;
use App\Tax\Calculator\DividendTaxCalculator;
use App\Tax\Calculator\HealthInsuranceCalculator;
use App\Tax\Calculator\IncomeTaxCalculator;
use App\Tax\Calculator\SocialInsuranceCalculator;
use App\Tax\Calculator\StockSalesCalculator;
use App\Tax\Calculator\TaxDividendInput;
use App\Tax\Calculator\TaxStockSaleInput;
use App\Tax\Calculator\TaxYearCalculationOrchestrator;
use App\Tax\DTO\TaxYearReportSnapshotDTO;
use App\Tax\Rules\TaxDividendModeEnum;
use App\Tax\Rules\TaxExpenseFlatRateEnum;
use App\Tax\Rules\TaxTreatyRateProvider;
use App\Tax\Rules\TaxYearRulesProvider;
use App\Tax\TaxYearReportJson;
use App\Test\UpdatedTestCase;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class TaxCalculatorsVerificationTest extends UpdatedTestCase
{

	private TaxYearRulesProvider $rulesProvider;

	private TaxTreatyRateProvider $treatyRateProvider;

	private BusinessIncomeCalculator $businessIncomeCalculator;

	private HealthInsuranceCalculator $healthInsuranceCalculator;

	private SocialInsuranceCalculator $socialInsuranceCalculator;

	private StockSalesCalculator $stockSalesCalculator;

	private DividendTaxCalculator $dividendTaxCalculator;

	private IncomeTaxCalculator $incomeTaxCalculator;

	private TaxYearCalculationOrchestrator $orchestrator;

	protected function setUp(): void
	{
		parent::setUp();

		$this->rulesProvider = new TaxYearRulesProvider();
		$this->treatyRateProvider = new TaxTreatyRateProvider();
		$this->businessIncomeCalculator = new BusinessIncomeCalculator();
		$this->healthInsuranceCalculator = new HealthInsuranceCalculator();
		$this->socialInsuranceCalculator = new SocialInsuranceCalculator();
		$this->stockSalesCalculator = new StockSalesCalculator();
		$this->dividendTaxCalculator = new DividendTaxCalculator($this->treatyRateProvider);
		$this->incomeTaxCalculator = new IncomeTaxCalculator($this->dividendTaxCalculator);
		$this->orchestrator = new TaxYearCalculationOrchestrator(
			$this->rulesProvider,
			$this->businessIncomeCalculator,
			$this->healthInsuranceCalculator,
			$this->socialInsuranceCalculator,
			$this->incomeTaxCalculator,
		);
	}

	public function testPlanExampleAYear2025AboveMinimumsAndSeptemberScenarioSwitch(): void
	{
		$rules2025 = $this->rulesProvider->getForYear(2025);
		$rules2026 = $this->rulesProvider->getForYear(2026);

		// § 7: 1 500 000 Kč, 60 % paušál -> 900 000 Kč výdaje, 600 000 Kč základ
		$section7Expenses = $this->businessIncomeCalculator->calculateFlatRateExpenses(
			1_500_000,
			TaxExpenseFlatRateEnum::RATE_60,
		);
		$section7Base = $this->businessIncomeCalculator->calculateTaxBase(
			1_500_000,
			TaxExpenseFlatRateEnum::RATE_60,
		);
		self::assertSame(900_000, $section7Expenses);
		self::assertSame(600_000, $section7Base);

		// § 10: 1 neosvobozený prodej za 160 000 Kč s výdajem 120 000 Kč -> 40 000 Kč
		$stockSales = $this->stockSalesCalculator->calculate([
			new TaxStockSaleInput(
				closedPositionId: 'sale-1',
				assetName: 'Apple',
				ticker: 'AAPL',
				pieces: 10,
				buyDate: new ImmutableDateTime('2024-06-01'),
				sellDate: new ImmutableDateTime('2025-08-15'),
				buyCurrency: 'CZK',
				buyAmountOriginal: 120_000.0,
				buyExchangeRate: 1.0,
				buyExchangeRateDate: '2024-06-01',
				buyAmountCzk: 120_000,
				sellCurrency: 'CZK',
				sellAmountOriginal: 160_000.0,
				sellExchangeRate: 1.0,
				sellExchangeRateDate: '2025-08-15',
				sellAmountCzk: 160_000,
				hasMissingExchangeRate: false,
			),
		], $rules2025);
		self::assertSame(40_000, $stockSales->taxBaseSection10Czk);

		$dividends = $this->dividendTaxCalculator->calculate([], TaxDividendModeEnum::STANDARD_SECTION_8);

		// Zářijový výhled k 30. 9. 2025:
		// FÚ: zaplaceno 24 000 + očekáváno 24 000 = 48 000
		// ZP: zaplaceno 28 800 + očekáváno 9 600 = 38 400
		// ČSSZ: zaplaceno 63 000 + očekáváno 21 000 = 84 000
		$health = $this->healthInsuranceCalculator->calculate(
			section7TaxBaseCzk: $section7Base,
			activeMonths: 12,
			yearRules: $rules2025,
			nextYearRules: $rules2026,
			paidAdvancesCzk: 28_800,
			expectedRemainingAdvancesCzk: 9_600,
			advancesSourceNote: 'test',
		);
		self::assertSame(40_500, $health['detail']->annualInsuranceCzk);
		self::assertSame(2_100, $health['summary']->settlementCzk);
		self::assertSame(3_375, $health['detail']->nextMonthlyAdvanceCzk);

		$social = $this->socialInsuranceCalculator->calculate(
			section7TaxBaseCzk: $section7Base,
			activeMonths: 12,
			yearRules: $rules2025,
			nextYearRules: $rules2026,
			paidAdvancesCzk: 63_000,
			expectedRemainingAdvancesCzk: 21_000,
			sicknessInsurancePaidCzk: 0,
			advancesSourceNote: 'test',
		);
		self::assertSame(96_360, $social['detail']->annualInsuranceCzk);
		self::assertSame(12_360, $social['summary']->settlementCzk);
		self::assertSame(8_030, $social['detail']->nextMonthlyAdvanceCzk);

		$incomeTax = $this->incomeTaxCalculator->calculate(
			section7TaxBaseCzk: $section7Base,
			section10TaxBaseCzk: $stockSales->taxBaseSection10Czk,
			dividends: $dividends,
			selectedDividendMode: TaxDividendModeEnum::STANDARD_SECTION_8,
			rawDonationsCzk: 0,
			pensionAndLifeDeductionCzk: 0,
			mortgageInterestDeductionCzk: 0,
			otherDeductionsCzk: 0,
			otherTaxCreditsCzk: 0,
			rules: $rules2025,
			paidAdvancesCzk: 24_000,
			expectedRemainingAdvancesCzk: 24_000,
			advancesSourceNote: 'test',
		);
		self::assertSame(65_160, $incomeTax['detail']->totalAnnualTaxCzk);
		self::assertSame(17_160, $incomeTax['summary']->settlementCzk);
		self::assertSame(59_160, $incomeTax['detail']->advanceBaseTaxWithoutSection10And16aCzk);
		self::assertSame(23_700, $incomeTax['detail']->nextAdvanceAmountCzk);
		self::assertSame(2, $incomeTax['detail']->nextAdvanceCountPerYear);

		// Celková rezerva = 17 160 + 2 100 + 12 360 = 31 620 Kč
		$baselineReserve = $incomeTax['summary']->payableShortfallCzk
			+ $health['summary']->payableShortfallCzk
			+ $social['summary']->payableShortfallCzk;
		self::assertSame(31_620, $baselineReserve);

		// Pokud se stejný prodej ukáže časově osvobozený -> daň klesne o 6 000 Kč, ale příští záloha DPFO zůstane 23 700 Kč
		$incomeTaxExemptSale = $this->incomeTaxCalculator->calculate(
			section7TaxBaseCzk: $section7Base,
			section10TaxBaseCzk: 0,
			dividends: $dividends,
			selectedDividendMode: TaxDividendModeEnum::STANDARD_SECTION_8,
			rawDonationsCzk: 0,
			pensionAndLifeDeductionCzk: 0,
			mortgageInterestDeductionCzk: 0,
			otherDeductionsCzk: 0,
			otherTaxCreditsCzk: 0,
			rules: $rules2025,
			paidAdvancesCzk: 24_000,
			expectedRemainingAdvancesCzk: 24_000,
			advancesSourceNote: 'test',
		);
		self::assertSame(59_160, $incomeTaxExemptSale['detail']->totalAnnualTaxCzk);
		self::assertSame(23_700, $incomeTaxExemptSale['detail']->nextAdvanceAmountCzk);

		// Přepnutí v zářijovém výhledu z 1 500 000 Kč na 1 650 000 Kč -> rezerva 31 620 -> 54 306 Kč
		$businessSection = $this->businessIncomeCalculator->calculate(
			items: [],
			flatRate: TaxExpenseFlatRateEnum::RATE_60,
			manualAdjustmentCzk: 1_500_000,
			manualAdjustmentNote: null,
			autoForecastPeriodDescription: 'test',
			autoForecastMonthsUsed: 9,
			customForecastAnnualIncomeCzk: null,
			useCustomForecast: false,
			isOpenYear: true,
		);

		$snapshot = new TaxYearReportSnapshotDTO(
			year: 2025,
			asOfDate: '2025-09-30',
			isOpenYear: true,
			isClosed: false,
			isEstimatedRules: false,
			reserveTargetDate: '2026-02-01',
			businessIncome: $businessSection,
			stockSales: $stockSales,
			dividends: $incomeTax['dividends'],
			healthInsuranceDetail: $health['detail'],
			socialInsuranceDetail: $social['detail'],
			incomeTaxDetail: $incomeTax['detail'],
			taxOfficeSummary: $incomeTax['summary'],
			healthInsuranceSummary: $health['summary'],
			socialInsuranceSummary: $social['summary'],
			totalAnnualLiabilityCzk: 202_020,
			totalPaidAdvancesCzk: 115_800,
			totalExpectedRemainingAdvancesCzk: 54_600,
			totalReserveToPrepareCzk: 31_620,
			totalExpectedOverpaymentsCzk: 0,
			totalRemainingCashflowThisYearAndSettlementCzk: 86_220,
			donations: [],
			payments: [],
			warnings: [],
		);

		// JSON round-trip test
		$decodedSnapshot = TaxYearReportJson::decode(
			TaxYearReportJson::encode($snapshot),
			TaxYearReportSnapshotDTO::class,
		);
		self::assertSame(31_620, $decodedSnapshot->totalReserveToPrepareCzk);

		$scenario = $this->orchestrator->recalculateScenario($decodedSnapshot, 1_650_000);
		self::assertSame(660_000, $scenario->section7TaxBaseCzk);
		self::assertSame(54_306, $scenario->totalReserveToPrepareCzk);
		self::assertSame(22_686, $scenario->reserveDifferenceFromBaselineCzk);
	}

	public function testPlanExampleBYear2026OnMinimums(): void
	{
		$rules2026 = $this->rulesProvider->getForYear(2026);
		$rules2027 = $this->rulesProvider->getForYear(2027);

		// ČSSZ: zaplacené zálohy 6 × 5 720 + 6 × 5 005 = 64 350 Kč
		$socialMinAdvances = $rules2026->getSocialMinAnnualAdvanceSum();
		self::assertSame(64_350, $socialMinAdvances);

		$social = $this->socialInsuranceCalculator->calculate(
			section7TaxBaseCzk: 200_000,
			activeMonths: 12,
			yearRules: $rules2026,
			nextYearRules: $rules2027,
			paidAdvancesCzk: $socialMinAdvances,
			expectedRemainingAdvancesCzk: 0,
			sicknessInsurancePaidCzk: 0,
			advancesSourceNote: 'test',
		);
		self::assertTrue($social['detail']->usedMinimumBase);
		self::assertSame(205_668, $social['detail']->usedAssessmentBaseCzk);
		self::assertSame(60_056, $social['detail']->annualInsuranceCzk);
		self::assertSame(-4_294, $social['summary']->settlementCzk);
		self::assertSame(4_294, $social['summary']->expectedOverpaymentCzk);
		self::assertSame(5_281, $social['detail']->nextMonthlyAdvanceCzk);

		// ZP: zaplacené zálohy 12 × 3 306 = 39 672 Kč
		$healthMinAdvances = $rules2026->getHealthMinAnnualAdvanceSum();
		self::assertSame(39_672, $healthMinAdvances);

		$health = $this->healthInsuranceCalculator->calculate(
			section7TaxBaseCzk: 200_000,
			activeMonths: 12,
			yearRules: $rules2026,
			nextYearRules: $rules2027,
			paidAdvancesCzk: $healthMinAdvances,
			expectedRemainingAdvancesCzk: 0,
			advancesSourceNote: 'test',
		);
		self::assertTrue($health['detail']->usedMinimumBase);
		self::assertSame(39_664, $health['detail']->annualInsuranceCzk);
		self::assertSame(-8, $health['summary']->settlementCzk);
		self::assertSame(8, $health['summary']->expectedOverpaymentCzk);
		self::assertSame(3_488, $health['detail']->nextMonthlyAdvanceCzk);
	}

	public function testFlatRateCapAnd23PercentTaxBracketAndSection38aThresholds(): void
	{
		$rules2025 = $this->rulesProvider->getForYear(2025);

		// Paušál 60 % strop 1 200 000 Kč při 2 200 000 Kč -> základ 1 000 000 Kč
		self::assertSame(
			1_200_000,
			$this->businessIncomeCalculator->calculateFlatRateExpenses(2_200_000, TaxExpenseFlatRateEnum::RATE_60),
		);
		self::assertSame(
			1_000_000,
			$this->businessIncomeCalculator->calculateTaxBase(2_200_000, TaxExpenseFlatRateEnum::RATE_60),
		);

		// Hranice § 38a: 30 000 / 30 001 / 150 000 / 150 001
		self::assertSame(0, $this->incomeTaxCalculator->calculateNextAdvances(30_000, $rules2025)['countPerYear']);
		$justOver30k = $this->incomeTaxCalculator->calculateNextAdvances(30_001, $rules2025);
		self::assertSame(2, $justOver30k['countPerYear']);
		self::assertSame(12_100, $justOver30k['amountCzk']);

		$at150k = $this->incomeTaxCalculator->calculateNextAdvances(150_000, $rules2025);
		self::assertSame(2, $at150k['countPerYear']);
		self::assertSame(60_000, $at150k['amountCzk']);

		$over150k = $this->incomeTaxCalculator->calculateNextAdvances(150_001, $rules2025);
		self::assertSame(4, $over150k['countPerYear']);
		self::assertSame(37_600, $over150k['amountCzk']);

		// 23% pásmo v roce 2025: hranice 1 676 052 Kč
		$dividends = $this->dividendTaxCalculator->calculate([], TaxDividendModeEnum::STANDARD_SECTION_8);
		$highIncome = $this->incomeTaxCalculator->calculate(
			section7TaxBaseCzk: 2_000_000,
			section10TaxBaseCzk: 0,
			dividends: $dividends,
			selectedDividendMode: TaxDividendModeEnum::STANDARD_SECTION_8,
			rawDonationsCzk: 0,
			pensionAndLifeDeductionCzk: 0,
			mortgageInterestDeductionCzk: 0,
			otherDeductionsCzk: 0,
			otherTaxCreditsCzk: 0,
			rules: $rules2025,
			paidAdvancesCzk: 0,
			expectedRemainingAdvancesCzk: 0,
			advancesSourceNote: 'test',
		);
		self::assertSame(1_676_052, $highIncome['detail']->baseIn15BracketCzk);
		self::assertSame(323_948, $highIncome['detail']->baseIn23BracketCzk);
		self::assertSame(251_408, $highIncome['detail']->taxIn15BracketCzk);
		self::assertSame(74_508, $highIncome['detail']->taxIn23BracketCzk);
	}

	public function testDonationsSection15Limits(): void
	{
		$rules2025 = $this->rulesProvider->getForYear(2025);
		$dividends = $this->dividendTaxCalculator->calculate([], TaxDividendModeEnum::STANDARD_SECTION_8);

		// Pod minimem (800 Kč při základu 600 000 Kč)
		$belowMin = $this->incomeTaxCalculator->calculate(
			section7TaxBaseCzk: 600_000,
			section10TaxBaseCzk: 0,
			dividends: $dividends,
			selectedDividendMode: TaxDividendModeEnum::STANDARD_SECTION_8,
			rawDonationsCzk: 800,
			pensionAndLifeDeductionCzk: 0,
			mortgageInterestDeductionCzk: 0,
			otherDeductionsCzk: 0,
			otherTaxCreditsCzk: 0,
			rules: $rules2025,
			paidAdvancesCzk: 0,
			expectedRemainingAdvancesCzk: 0,
			advancesSourceNote: 'test',
		);
		self::assertTrue($belowMin['detail']->donationsBelowMinimum);
		self::assertSame(0, $belowMin['detail']->deductibleDonationsCzk);

		// Platný dar 10 000 Kč (Darujme.cz / Donio.cz) -> sníží daň o 1 500 Kč (59 160 -> 57 660 Kč)
		$validDonation = $this->incomeTaxCalculator->calculate(
			section7TaxBaseCzk: 600_000,
			section10TaxBaseCzk: 0,
			dividends: $dividends,
			selectedDividendMode: TaxDividendModeEnum::STANDARD_SECTION_8,
			rawDonationsCzk: 10_000,
			pensionAndLifeDeductionCzk: 0,
			mortgageInterestDeductionCzk: 0,
			otherDeductionsCzk: 0,
			otherTaxCreditsCzk: 0,
			rules: $rules2025,
			paidAdvancesCzk: 0,
			expectedRemainingAdvancesCzk: 0,
			advancesSourceNote: 'test',
		);
		self::assertFalse($validDonation['detail']->donationsBelowMinimum);
		self::assertFalse($validDonation['detail']->donationsCappedAtMax);
		self::assertSame(10_000, $validDonation['detail']->deductibleDonationsCzk);
		self::assertSame(57_660, $validDonation['detail']->totalAnnualTaxCzk);

		// Přes strop 30 % ze 600 000 = 180 000 Kč
		$cappedDonation = $this->incomeTaxCalculator->calculate(
			section7TaxBaseCzk: 600_000,
			section10TaxBaseCzk: 0,
			dividends: $dividends,
			selectedDividendMode: TaxDividendModeEnum::STANDARD_SECTION_8,
			rawDonationsCzk: 250_000,
			pensionAndLifeDeductionCzk: 0,
			mortgageInterestDeductionCzk: 0,
			otherDeductionsCzk: 0,
			otherTaxCreditsCzk: 0,
			rules: $rules2025,
			paidAdvancesCzk: 0,
			expectedRemainingAdvancesCzk: 0,
			advancesSourceNote: 'test',
		);
		self::assertTrue($cappedDonation['detail']->donationsCappedAtMax);
		self::assertSame(180_000, $cappedDonation['detail']->deductibleDonationsCzk);

		// Od roku 2027 se strop darů pro fyzické osoby vrací na standardních 15 % ZD
		$rules2027 = $this->rulesProvider->getForYear(2027);
		self::assertSame(0.15, $rules2027->giftMaxBasePercentage);
		$cappedDonation2027 = $this->incomeTaxCalculator->calculate(
			section7TaxBaseCzk: 1_000_000,
			section10TaxBaseCzk: 0,
			dividends: $dividends,
			selectedDividendMode: TaxDividendModeEnum::STANDARD_SECTION_8,
			rawDonationsCzk: 300_000,
			pensionAndLifeDeductionCzk: 0,
			mortgageInterestDeductionCzk: 0,
			otherDeductionsCzk: 0,
			otherTaxCreditsCzk: 0,
			rules: $rules2027,
			paidAdvancesCzk: 0,
			expectedRemainingAdvancesCzk: 0,
			advancesSourceNote: 'test',
		);
		self::assertTrue($cappedDonation2027['detail']->donationsCappedAtMax);
		self::assertSame(150_000, $cappedDonation2027['detail']->deductibleDonationsCzk);

		// Smluvní stropy pro Francii a Nizozemsko (10 % pro portfoliové dividendy)
		self::assertSame(0.10, $this->treatyRateProvider->getTreatyMaxRate('FR'));
		self::assertSame(0.10, $this->treatyRateProvider->getTreatyMaxRate('NL'));
	}

	public function testStockSalesValueTestTimeTestAnniversaryLeapYear40mCapAnd38v(): void
	{
		$rules2025 = $this->rulesProvider->getForYear(2025);
		$rules2026 = $this->rulesProvider->getForYear(2026);

		// Přesně 100 000 Kč -> osvobozeno hodnotovým testem
		$at100k = $this->stockSalesCalculator->calculate([
			$this->createSaleInput('1', '2025-01-10', '2025-06-10', 60_000, 100_000),
		], $rules2025);
		self::assertTrue($at100k->passedValueTest);
		self::assertSame(0, $at100k->taxBaseSection10Czk);

		// 100 001 Kč -> neosvobozeno hodnotovým testem
		$over100k = $this->stockSalesCalculator->calculate([
			$this->createSaleInput('1', '2025-01-10', '2025-06-10', 60_000, 100_001),
		], $rules2025);
		self::assertFalse($over100k->passedValueTest);
		self::assertSame(40_001, $over100k->taxBaseSection10Czk);

		// Ztráta -> základ § 10 je 0 Kč (ne záporný)
		$loss = $this->stockSalesCalculator->calculate([
			$this->createSaleInput('1', '2025-01-10', '2025-06-10', 150_000, 120_000),
		], $rules2025);
		self::assertSame(-30_000, $loss->rawTaxableGainCzk);
		self::assertSame(0, $loss->taxBaseSection10Czk);

		// Časový test: přesně 3. výročí NE, den poté ANO, přestupný rok 29. 2. 2020 -> 28. 2. 2023 NE, 1. 3. 2023 ANO
		self::assertFalse($this->stockSalesCalculator->hasPassedTimeTest(
			new ImmutableDateTime('2022-05-10'),
			new ImmutableDateTime('2025-05-10'),
			3,
		));
		self::assertTrue($this->stockSalesCalculator->hasPassedTimeTest(
			new ImmutableDateTime('2022-05-10'),
			new ImmutableDateTime('2025-05-11'),
			3,
		));
		self::assertFalse($this->stockSalesCalculator->hasPassedTimeTest(
			new ImmutableDateTime('2020-02-29'),
			new ImmutableDateTime('2023-02-28'),
			3,
		));
		self::assertTrue($this->stockSalesCalculator->hasPassedTimeTest(
			new ImmutableDateTime('2020-02-29'),
			new ImmutableDateTime('2023-03-01'),
			3,
		));

		// Dlouhé kusy 90 000 Kč + krátké 20 000 Kč (celkem 110 000 Kč): dlouhé osvobozeny časem, krátké zdaněny
		$mixed = $this->stockSalesCalculator->calculate([
			$this->createSaleInput('long', '2020-01-01', '2025-06-01', 50_000, 90_000),
			$this->createSaleInput('short', '2024-06-01', '2025-06-01', 12_000, 20_000),
		], $rules2025);
		self::assertFalse($mixed->passedValueTest);
		self::assertSame(90_000, $mixed->timeTestExemptProceedsCzk);
		self::assertSame(8_000, $mixed->taxBaseSection10Czk);

		// Strop 40 mil. Kč v roce 2025 vs zrušení v roce 2026 + § 38v oznámení nad 5 mil. Kč
		$largeSale = [$this->createSaleInput('big', '2018-01-01', '2025-06-01', 10_000_000, 45_000_000)];
		$res2025 = $this->stockSalesCalculator->calculate($largeSale, $rules2025);
		$res2026 = $this->stockSalesCalculator->calculate($largeSale, $rules2026);
		self::assertTrue($res2025->exceededTimeTestCap);
		self::assertFalse($res2026->exceededTimeTestCap);
		self::assertSame(1, $res2025->notification38vItemCount);
	}

	public function testDividendsFiveCountriesAndSeparate16aMode(): void
	{
		$rules2025 = $this->rulesProvider->getForYear(2025);

		// ČR, USA, Německo (vyšší srážka 26.375 %), Polsko (19 % srážka, strop 5 %), Rakousko (27.5 %, strop 10 %)
		$dividends = $this->dividendTaxCalculator->calculate([
			$this->createDividendInput('cz', 'CZ', 10_000, 1_500),
			$this->createDividendInput('us', 'US', 10_000, 1_500),
			$this->createDividendInput('de', 'DE', 10_000, 2_638),
			$this->createDividendInput('pl', 'PL', 10_000, 1_900),
			$this->createDividendInput('at', 'AT', 10_000, 2_750),
		], TaxDividendModeEnum::SEPARATE_BASE_16A);

		// České dividendy odděleny
		self::assertSame(10_000, $dividends->domesticGrossCzk);
		self::assertSame(40_000, $dividends->foreignGrossCzk);
		// Uznatelný zápočet: US 1 500 + DE 1 500 + PL 500 + AT 1 000 = 4 500 Kč
		self::assertSame(4_500, $dividends->foreignCreditableTaxCzk);
		// Česká daň v § 16a: 15 % z 40 000 = 6 000 − 4 500 = 1 500 Kč (0 US + 0 DE + 1 000 PL + 500 AT)
		self::assertSame(1_500, $dividends->separateBaseTaxAfterCreditCzk);

		// Dvě americké dividendy po 10 000 Kč se srážkami 3 000 Kč a 0 Kč -> max 1 500 Kč použitelného zápočtu
		$twoUs = $this->dividendTaxCalculator->calculate([
			$this->createDividendInput('us1', 'US', 10_000, 3_000),
			$this->createDividendInput('us2', 'US', 10_000, 0),
		], TaxDividendModeEnum::SEPARATE_BASE_16A);
		self::assertSame(1_500, $twoUs->foreignCreditableTaxCzk);
		self::assertSame(1_500, $twoUs->foreignExcessTaxCzk);

		// V 23% pásmu je § 16a (15 %) výhodnější než běžný § 8 (23 %)
		$usIn23Bracket = $this->dividendTaxCalculator->calculate([
			$this->createDividendInput('us-high', 'US', 100_000, 15_000),
		], TaxDividendModeEnum::SEPARATE_BASE_16A);
		$taxResult = $this->incomeTaxCalculator->calculate(
			section7TaxBaseCzk: 2_000_000,
			section10TaxBaseCzk: 0,
			dividends: $usIn23Bracket,
			selectedDividendMode: TaxDividendModeEnum::SEPARATE_BASE_16A,
			rawDonationsCzk: 0,
			pensionAndLifeDeductionCzk: 0,
			mortgageInterestDeductionCzk: 0,
			otherDeductionsCzk: 0,
			otherTaxCreditsCzk: 0,
			rules: $rules2025,
			paidAdvancesCzk: 0,
			expectedRemainingAdvancesCzk: 0,
			advancesSourceNote: 'test',
		);
		self::assertLessThan(
			$taxResult['detail']->alternativeModeTotalAnnualTaxCzk,
			$taxResult['detail']->totalAnnualTaxCzk,
		);
	}

	private function createSaleInput(
		string $id,
		string $buyDate,
		string $sellDate,
		int $buyAmountCzk,
		int $sellAmountCzk,
	): TaxStockSaleInput
	{
		return new TaxStockSaleInput(
			closedPositionId: $id,
			assetName: 'Test Stock',
			ticker: 'TST',
			pieces: 10,
			buyDate: new ImmutableDateTime($buyDate),
			sellDate: new ImmutableDateTime($sellDate),
			buyCurrency: 'CZK',
			buyAmountOriginal: (float) $buyAmountCzk,
			buyExchangeRate: 1.0,
			buyExchangeRateDate: $buyDate,
			buyAmountCzk: $buyAmountCzk,
			sellCurrency: 'CZK',
			sellAmountOriginal: (float) $sellAmountCzk,
			sellExchangeRate: 1.0,
			sellExchangeRateDate: $sellDate,
			sellAmountCzk: $sellAmountCzk,
			hasMissingExchangeRate: false,
		);
	}

	private function createDividendInput(
		string $id,
		string $countryCode,
		int $grossCzk,
		int $withheldTaxCzk,
	): TaxDividendInput
	{
		return new TaxDividendInput(
			dividendRecordId: $id,
			assetName: 'Company ' . $countryCode,
			ticker: 'T' . $countryCode,
			countryCode: $countryCode,
			paymentDate: '2025-06-15',
			currency: 'CZK',
			grossAmountOriginal: (float) $grossCzk,
			withheldTaxRatePercentage: $grossCzk > 0 ? $withheldTaxCzk / $grossCzk * 100 : 0.0,
			withheldTaxOriginal: (float) $withheldTaxCzk,
			exchangeRate: 1.0,
			exchangeRateDate: '2025-06-15',
			grossCzk: $grossCzk,
			withheldTaxCzk: $withheldTaxCzk,
			countryInferredWithoutIsin: false,
			hasMissingExchangeRate: false,
		);
	}

}
