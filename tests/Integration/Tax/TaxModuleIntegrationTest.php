<?php

declare(strict_types = 1);

namespace App\Test\Integration\Tax;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Cash\Bank\Account\BankAccount;
use App\Cash\Bank\Account\BankAccountTypeEnum;
use App\Cash\Bank\BankSourceEnum;
use App\Cash\Bank\BankTransactionType;
use App\Cash\Expense\Bank\BankExpense;
use App\Cash\Expense\Category\ExpenseCategory;
use App\Cash\Expense\Category\ExpenseCategoryEnum;
use App\Cash\Expense\Tag\ExpenseTag;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncome;
use App\Currency\CurrencyConversion;
use App\Currency\CurrencyEnum;
use App\Currency\CurrencySourceEnum;
use App\Doctrine\NoEntityFoundException;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Dividend\Record\StockAssetDividendRecord;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Dividend\StockAssetDividendSourceEnum;
use App\Stock\Position\Closed\StockClosedPosition;
use App\Stock\Position\StockPosition;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Tax\Payment\TaxPaymentRoleEnum;
use App\Tax\TaxYearProfileFacade;
use App\Tax\TaxYearReport;
use App\Tax\TaxYearReportBuilder;
use App\Tax\TaxYearReportFacade;
use App\Tax\TaxYearReportRepository;
use App\Tax\TaxYearReportStatusEnum;
use App\Test\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

class TaxModuleIntegrationTest extends IntegrationTestCase
{

	private AppAdmin $owner;

	private AppAdmin $otherOwner;

	private EntityManagerInterface $entityManager;

	private CurrentAppAdminGetter $currentAppAdminGetter;

	private TaxYearProfileFacade $profileFacade;

	private TaxYearReportFacade $reportFacade;

	private TaxYearReportBuilder $reportBuilder;

	private TaxYearReportRepository $reportRepository;

	protected function setUp(): void
	{
		parent::setUp();

		$this->entityManager = $this->getService(EntityManagerInterface::class);
		$this->currentAppAdminGetter = $this->getService(CurrentAppAdminGetter::class);
		$this->profileFacade = $this->getService(TaxYearProfileFacade::class);
		$this->reportFacade = $this->getService(TaxYearReportFacade::class);
		$this->reportBuilder = $this->getService(TaxYearReportBuilder::class);
		$this->reportRepository = $this->getService(TaxYearReportRepository::class);

		$now = new ImmutableDateTime('2026-02-15 10:00:00');
		$suffix = Uuid::uuid4()->toString();

		$this->owner = new AppAdmin(
			'tax-owner-' . $suffix,
			'tax-owner-' . $suffix,
			'tax-owner-' . $suffix . '@example.test',
			'unused',
			$now,
			false,
			false,
		);
		$this->otherOwner = new AppAdmin(
			'tax-other-' . $suffix,
			'tax-other-' . $suffix,
			'tax-other-' . $suffix . '@example.test',
			'unused',
			$now,
			false,
			false,
		);

		$this->entityManager->persist($this->owner);
		$this->entityManager->persist($this->otherOwner);
		$this->entityManager->flush();

		$this->currentAppAdminGetter->setApiAppAdmin($this->owner);
	}

	public function testGeneratesCompleteYear2025SnapshotAndIsolatesOwners(): void
	{
		$now = new ImmutableDateTime('2026-02-15 10:00:00');

		// 1. WorkMonthlyIncome for 2025 (Dec 2024 + Jan..Nov 2025 = 12 * 125 000 = 1 500 000 Kč)
		$this->entityManager->persist(new WorkMonthlyIncome($this->owner, 2024, 12, 125.0, 1_000, $now));
		for ($month = 1; $month <= 11; $month++) {
			$this->entityManager->persist(new WorkMonthlyIncome($this->owner, 2025, $month, 125.0, 1_000, $now));
		}

		// Other owner's work income must never leak
		$this->entityManager->persist(new WorkMonthlyIncome($this->otherOwner, 2025, 1, 500.0, 2_000, $now));

		// 2. Bank account, tax tags and expenses (including Jan 5, 2026 health insurance for Dec 2025)
		$account = new BankAccount($this->owner, 'Personal', 'KB', BankAccountTypeEnum::PERSONAL);
		$this->entityManager->persist($account);

		$healthTag = new ExpenseTag(
			$this->owner,
			'VZP',
			null,
			null,
			[],
			$now,
			true,
			TaxPaymentRoleEnum::HEALTH_INSURANCE_ADVANCE,
		);
		$socialTag = new ExpenseTag(
			$this->owner,
			'CSSZ',
			null,
			null,
			[],
			$now,
			true,
			TaxPaymentRoleEnum::SOCIAL_INSURANCE_ADVANCE,
		);
		$fuTag = new ExpenseTag(
			$this->owner,
			'FU zaloha',
			null,
			null,
			[],
			$now,
			true,
			TaxPaymentRoleEnum::INCOME_TAX_ADVANCE,
		);
		$this->entityManager->persist($healthTag);
		$this->entityManager->persist($socialTag);
		$this->entityManager->persist($fuTag);

		// 11 health payments in 2025 + 1 on 2026-01-05 (auto-assigned to 2025) = 12 * 3 200 = 38 400 Kč
		for ($month = 2; $month <= 12; $month++) {
			$exp = new BankExpense(
				'vzp-2025-' . $month,
				BankSourceEnum::KOMERCNI_BANKA,
				BankTransactionType::TRANSACTION,
				-3_200.0,
				CurrencyEnum::CZK,
				new ImmutableDateTime(sprintf('2025-%02d-05', $month)),
				new ImmutableDateTime(sprintf('2025-%02d-05', $month)),
				'VZP zaloha',
				$now,
				$account,
			);
			$exp->addOtherTag($healthTag);
			$this->entityManager->persist($exp);
		}

		$decHealthPaidInJan = new BankExpense(
			'vzp-2025-dec-in-jan',
			BankSourceEnum::KOMERCNI_BANKA,
			BankTransactionType::TRANSACTION,
			-3_200.0,
			CurrencyEnum::CZK,
			new ImmutableDateTime('2026-01-05'),
			new ImmutableDateTime('2026-01-05'),
			'VZP prosinec',
			$now,
			$account,
		);
		$decHealthPaidInJan->addOtherTag($healthTag);
		$this->entityManager->persist($decHealthPaidInJan);

		// Social advances: 12 * 7 000 = 84 000 Kč
		for ($month = 1; $month <= 12; $month++) {
			$exp = new BankExpense(
				'cssz-2025-' . $month,
				BankSourceEnum::KOMERCNI_BANKA,
				BankTransactionType::TRANSACTION,
				-7_000.0,
				CurrencyEnum::CZK,
				new ImmutableDateTime(sprintf('2025-%02d-15', $month)),
				new ImmutableDateTime(sprintf('2025-%02d-15', $month)),
				'CSSZ zaloha',
				$now,
				$account,
			);
			$exp->addOtherTag($socialTag);
			$this->entityManager->persist($exp);
		}

		// FU advances: 2 * 24 000 = 48 000 Kč
		foreach ([6, 12] as $month) {
			$exp = new BankExpense(
				'fu-2025-' . $month,
				BankSourceEnum::KOMERCNI_BANKA,
				BankTransactionType::TRANSACTION,
				-24_000.0,
				CurrencyEnum::CZK,
				new ImmutableDateTime(sprintf('2025-%02d-10', $month)),
				new ImmutableDateTime(sprintf('2025-%02d-10', $month)),
				'FU zaloha',
				$now,
				$account,
			);
			$exp->addOtherTag($fuTag);
			$this->entityManager->persist($exp);
		}

		// 3. CNB exchange rates for USD -> CZK
		$this->entityManager->persist(new CurrencyConversion(
			CurrencyEnum::USD,
			CurrencyEnum::CZK,
			24.0,
			CurrencySourceEnum::CNB,
			$now,
			new ImmutableDateTime('2024-06-01'),
		));
		$this->entityManager->persist(new CurrencyConversion(
			CurrencyEnum::USD,
			CurrencyEnum::CZK,
			25.0,
			CurrencySourceEnum::CNB,
			$now,
			new ImmutableDateTime('2025-08-15'),
		));

		// 4. Stock closed position: bought 5 000 USD @ 24 = 120 000 Kč, sold 6 400 USD @ 25 = 160 000 Kč -> +40 000 Kč
		$asset = new StockAsset(
			'Apple Inc.',
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			'AAPL-' . substr(Uuid::uuid4()->toString(), 0, 6),
			StockAssetExchange::NASDAQ,
			CurrencyEnum::USD,
			$now,
			'US0378331005',
			StockAssetDividendSourceEnum::WEB,
			15.0,
			CurrencyEnum::USD,
			false,
			false,
			false,
		);
		$this->entityManager->persist($asset);

		$position = new StockPosition(
			$this->owner,
			$asset,
			100,
			50.0,
			new ImmutableDateTime('2024-06-01'),
			new AssetPriceEmbeddable(5_000.0, CurrencyEnum::USD),
			false,
			$now,
		);
		$closed = new StockClosedPosition(
			$position,
			64.0,
			new ImmutableDateTime('2025-08-15'),
			false,
			new AssetPriceEmbeddable(6_400.0, CurrencyEnum::USD),
			$now,
		);
		$position->closePosition($closed);
		$this->entityManager->persist($position);
		$this->entityManager->persist($closed);

		// 5. US dividend: 400 USD @ 25 = 10 000 Kč gross, 15% US withholding = 1 500 Kč (full credit)
		$dividend = new StockAssetDividend(
			$asset,
			new ImmutableDateTime('2025-08-01'),
			new ImmutableDateTime('2025-08-15'),
			null,
			CurrencyEnum::USD,
			4.0,
			$now,
		);
		$dividendRecord = new StockAssetDividendRecord(
			$dividend,
			100,
			400.0,
			CurrencyEnum::USD,
			null,
			null,
			$now,
			$this->owner,
		);
		$this->entityManager->persist($dividend);
		$this->entityManager->persist($dividendRecord);

		// 6. Manual donation: Darujme.cz 10 000 Kč
		$this->entityManager->flush();
		$this->profileFacade->addDonation(
			2025,
			'Darujme.cz',
			10_000,
			new ImmutableDateTime('2025-11-10'),
			'Potvrzeni #1',
		);

		// Generate report synchronously (same code path as JobRequest consumer)
		$report = $this->reportFacade->generateSync(2025, $this->owner);
		$reportId = $report->getId();
		$this->entityManager->clear();

		$loaded = $this->reportRepository->getById($reportId, $this->owner);
		self::assertSame(TaxYearReportStatusEnum::COMPLETED, $loaded->getStatus());
		$snapshot = $loaded->getSnapshot();
		self::assertNotNull($snapshot);

		self::assertSame(1_500_000, $snapshot->businessIncome->usedAnnualIncomeCzk);
		self::assertSame(600_000, $snapshot->businessIncome->taxBaseCzk);
		self::assertSame(40_000, $snapshot->stockSales->taxBaseSection10Czk);
		self::assertSame(10_000, $snapshot->dividends->foreignGrossCzk);
		// With a 10 000 CZK donation reducing tax from 97 500 to 96 000 on 650 000 base,
		// § 38f Czech credit limit in standard § 8 is ceil(96 000 * 10 000 / 650 000) = 1 477 CZK,
		// while § 16a allows full 1 500 CZK credit.
		self::assertSame(1_477, $snapshot->dividends->allowedCreditStandardCzk);
		self::assertSame(1_500, $snapshot->dividends->allowedCreditSeparate16aCzk);
		self::assertSame(10_000, $snapshot->incomeTaxDetail->deductibleDonationsCzk);

		// Health & Social match Example A exactly
		self::assertSame(40_500, $snapshot->healthInsuranceSummary->annualLiabilityCzk);
		self::assertSame(38_400, $snapshot->healthInsuranceSummary->paidAdvancesCzk);
		self::assertSame(2_100, $snapshot->healthInsuranceSummary->settlementCzk);

		self::assertSame(96_360, $snapshot->socialInsuranceSummary->annualLiabilityCzk);
		self::assertSame(84_000, $snapshot->socialInsuranceSummary->paidAdvancesCzk);
		self::assertSame(12_360, $snapshot->socialInsuranceSummary->settlementCzk);

		// Income tax in standard § 8: 96 000 - 1 477 credit - 30 840 = 63 683 CZK (vs 63 660 CZK in § 16a)
		self::assertSame(63_683, $snapshot->taxOfficeSummary->annualLiabilityCzk);
		self::assertSame(63_660, $snapshot->incomeTaxDetail->alternativeModeTotalAnnualTaxCzk);
		self::assertSame(48_000, $snapshot->taxOfficeSummary->paidAdvancesCzk);
		self::assertSame(15_683, $snapshot->taxOfficeSummary->settlementCzk);

		// Owner isolation check: otherOwner cannot load owner's report
		$this->expectException(NoEntityFoundException::class);
		$this->reportRepository->getById($reportId, $this->otherOwner);
	}

	public function testOtherTagRoleOverridesParentMainTagAndOpenYearAdvances(): void
	{
		$now = new ImmutableDateTime('2026-11-24 10:00:00');

		// 1. Completed previous year 2025 with 2x semi-annual income tax advances
		$this->entityManager->persist(new WorkMonthlyIncome($this->owner, 2024, 12, 100.0, 1_000, $now));
		for ($month = 1; $month <= 11; $month++) {
			$this->entityManager->persist(new WorkMonthlyIncome($this->owner, 2025, $month, 100.0, 1_000, $now));
		}

		$this->entityManager->flush();
		$this->reportFacade->generateSync(2025, $this->owner);

		// 2. Open year 2026 work income (Dec 2025 + Jan..Oct 2026)
		$this->entityManager->persist(new WorkMonthlyIncome($this->owner, 2025, 12, 100.0, 1_000, $now));
		for ($month = 1; $month <= 10; $month++) {
			$this->entityManager->persist(new WorkMonthlyIncome($this->owner, 2026, $month, 100.0, 1_000, $now));
		}

		$account = new BankAccount($this->owner, 'Personal', 'KB', BankAccountTypeEnum::PERSONAL);
		$this->entityManager->persist($account);

		$taxCategory = new ExpenseCategory('Dan', ExpenseCategoryEnum::TAX, $now);
		$this->entityManager->persist($taxCategory);

		$socialParentTag = new ExpenseTag(
			$this->owner,
			'OSSZ',
			$taxCategory,
			null,
			[],
			$now,
			true,
			TaxPaymentRoleEnum::SOCIAL_INSURANCE_ADVANCE,
		);
		$socialSettlementSubTag = new ExpenseTag(
			$this->owner,
			'OSSZ vyuctovani',
			null,
			$socialParentTag,
			[],
			$now,
			true,
			TaxPaymentRoleEnum::SOCIAL_INSURANCE_SETTLEMENT,
		);
		$healthTag = new ExpenseTag(
			$this->owner,
			'VZP',
			$taxCategory,
			null,
			[],
			$now,
			true,
			TaxPaymentRoleEnum::HEALTH_INSURANCE_ADVANCE,
		);
		$fuTag = new ExpenseTag(
			$this->owner,
			'DPFO',
			$taxCategory,
			null,
			[],
			$now,
			true,
			TaxPaymentRoleEnum::INCOME_TAX_ADVANCE,
		);
		$this->entityManager->persist($socialParentTag);
		$this->entityManager->persist($socialSettlementSubTag);
		$this->entityManager->persist($healthTag);
		$this->entityManager->persist($fuTag);

		// Spring OSSZ settlement has mainTag=OSSZ (advance) + otherTag=OSSZ vyuctovani (settlement)
		// More specific otherTag must override parent mainTag so it is NOT counted as a 2026 advance.
		$settlementExp = new BankExpense(
			'ossz-settle-2026',
			BankSourceEnum::KOMERCNI_BANKA,
			BankTransactionType::TRANSACTION,
			-10_000.0,
			CurrencyEnum::CZK,
			new ImmutableDateTime('2026-02-10'),
			new ImmutableDateTime('2026-02-10'),
			'OSSZ settlement',
			$now,
			$account,
		);
		$settlementExp->setMainTag($socialParentTag);
		$settlementExp->addOtherTag($socialSettlementSubTag);
		$this->entityManager->persist($settlementExp);

		// 10 monthly VZP (3 500 Kč) and OSSZ (6 000 Kč) advances paid Jan..Oct 2026 (Nov not paid yet on Nov 24)
		for ($month = 1; $month <= 10; $month++) {
			$socialExp = new BankExpense(
				'ossz-2026-' . $month,
				BankSourceEnum::KOMERCNI_BANKA,
				BankTransactionType::TRANSACTION,
				-6_000.0,
				CurrencyEnum::CZK,
				new ImmutableDateTime(sprintf('2026-%02d-25', $month)),
				new ImmutableDateTime(sprintf('2026-%02d-25', $month)),
				'OSSZ advance',
				$now,
				$account,
			);
			$socialExp->setMainTag($socialParentTag);
			$this->entityManager->persist($socialExp);

			$healthExp = new BankExpense(
				'vzp-2026-' . $month,
				BankSourceEnum::KOMERCNI_BANKA,
				BankTransactionType::TRANSACTION,
				-3_500.0,
				CurrencyEnum::CZK,
				new ImmutableDateTime(sprintf('2026-%02d-25', $month)),
				new ImmutableDateTime(sprintf('2026-%02d-25', $month)),
				'VZP advance',
				$now,
				$account,
			);
			$healthExp->setMainTag($healthTag);
			$this->entityManager->persist($healthExp);
		}

		// Both semi-annual DPFO advances paid early (June 15 and Nov 15) -> remaining must be 0
		foreach (['2026-06-15', '2026-11-15'] as $idx => $dateStr) {
			$fuExp = new BankExpense(
				'fu-2026-' . $idx,
				BankSourceEnum::KOMERCNI_BANKA,
				BankTransactionType::TRANSACTION,
				15_000.0,
				CurrencyEnum::CZK,
				new ImmutableDateTime($dateStr),
				new ImmutableDateTime($dateStr),
				'DPFO advance',
				$now,
				$account,
			);
			$fuExp->setMainTag($fuTag);
			$this->entityManager->persist($fuExp);
		}

		$this->entityManager->flush();

		$report2026 = new TaxYearReport($this->owner, 2026, $now, $now);
		$snapshot = $this->reportBuilder->build($report2026);

		// Settlement excluded from advances: paid = 10 * 6 000 = 60 000, remaining = 2 * 6 000 = 12 000 (Nov + Dec)
		self::assertSame(60_000, $snapshot->socialInsuranceSummary->paidAdvancesCzk);
		self::assertSame(12_000, $snapshot->socialInsuranceSummary->expectedRemainingAdvancesCzk);

		// Health: paid = 10 * 3 500 = 35 000, remaining = 2 * 3 500 = 7 000 (Nov + Dec)
		self::assertSame(35_000, $snapshot->healthInsuranceSummary->paidAdvancesCzk);
		self::assertSame(7_000, $snapshot->healthInsuranceSummary->expectedRemainingAdvancesCzk);

		// DPFO: 2 semi-annual advances paid early (30 000), so remaining = 0
		self::assertSame(30_000, $snapshot->taxOfficeSummary->paidAdvancesCzk);
		self::assertSame(0, $snapshot->taxOfficeSummary->expectedRemainingAdvancesCzk);
	}

	public function testDecemberHealthAdvanceJanuaryZeroPaidEffectiveDateOrderingBlankWorkRowAndOpenPreviousYearWarning(): void
	{
		$now = new ImmutableDateTime('2026-12-15 10:00:00');

		// 1. Add manual donation with null date (must succeed) and wrong year (must fail)
		$donation = $this->profileFacade->addDonation(2026, 'Darujme.cz', 2_000, null, null);
		self::assertNull($donation->getDonatedAt());

		$wrongYearFailed = false;
		try {
			$this->profileFacade->addDonation(2026, 'Donio.cz', 1_000, new ImmutableDateTime('2025-12-20'), null);
		} catch (InvalidArgumentException) {
			$wrongYearFailed = true;
		}

		self::assertTrue($wrongYearFailed);

		// 2. WorkMonthlyIncome with a blank 0h row in March 2026 -> excluded from forecast average + warning
		$this->entityManager->persist(new WorkMonthlyIncome($this->owner, 2025, 12, 100.0, 1_000, $now));
		$this->entityManager->persist(new WorkMonthlyIncome($this->owner, 2026, 1, 100.0, 1_000, $now));
		$this->entityManager->persist(new WorkMonthlyIncome($this->owner, 2026, 2, 100.0, 1_000, $now));
		$this->entityManager->persist(new WorkMonthlyIncome($this->owner, 2026, 3, 0.0, 1_000, $now));
		for ($month = 4; $month <= 10; $month++) {
			$this->entityManager->persist(new WorkMonthlyIncome($this->owner, 2026, $month, 100.0, 1_000, $now));
		}

		// 3. Bank account + VZP & OSSZ tags
		$account = new BankAccount($this->owner, 'Personal', 'KB', BankAccountTypeEnum::PERSONAL);
		$this->entityManager->persist($account);

		$taxCategory = new ExpenseCategory('Dan', ExpenseCategoryEnum::TAX, $now);
		$this->entityManager->persist($taxCategory);

		$healthTag = new ExpenseTag(
			$this->owner,
			'VZP',
			$taxCategory,
			null,
			[],
			$now,
			true,
			TaxPaymentRoleEnum::HEALTH_INSURANCE_ADVANCE,
		);
		$socialTag = new ExpenseTag(
			$this->owner,
			'CSSZ',
			$taxCategory,
			null,
			[],
			$now,
			true,
			TaxPaymentRoleEnum::SOCIAL_INSURANCE_ADVANCE,
		);
		$this->entityManager->persist($healthTag);
		$this->entityManager->persist($socialTag);

		// 4. 11 VZP payments paid on 5th of Feb..Dec 2026 (covering Jan..Nov 2026).
		// On Dec 15, December VZP advance (due Jan 8, 2027) must still count as 1 remaining month!
		for ($month = 2; $month <= 12; $month++) {
			$amount = $month === 12 ? -3_500.0 : -3_306.0;
			$exp = new BankExpense(
				'vzp-arrears-2026-' . $month,
				BankSourceEnum::KOMERCNI_BANKA,
				BankTransactionType::TRANSACTION,
				$amount,
				CurrencyEnum::CZK,
				new ImmutableDateTime(sprintf('2026-%02d-05', $month)),
				// For Dec payment leave transactionDate NULL to verify effective-date ordering
				$month === 12 ? null : new ImmutableDateTime(sprintf('2026-%02d-05', $month)),
				'VZP zaloha',
				$now,
				$account,
			);
			$exp->setMainTag($healthTag);
			$this->entityManager->persist($exp);
		}

		// 5. Two OSSZ payments: October with transactionDate (6 000 Kč) and November with NULL transactionDate (6 500 Kč)
		$octSocial = new BankExpense(
			'cssz-oct',
			BankSourceEnum::KOMERCNI_BANKA,
			BankTransactionType::TRANSACTION,
			-6_000.0,
			CurrencyEnum::CZK,
			new ImmutableDateTime('2026-10-20'),
			new ImmutableDateTime('2026-10-20'),
			'CSSZ rijen',
			$now,
			$account,
		);
		$octSocial->setMainTag($socialTag);
		$novSocial = new BankExpense(
			'cssz-nov',
			BankSourceEnum::KOMERCNI_BANKA,
			BankTransactionType::TRANSACTION,
			-6_500.0,
			CurrencyEnum::CZK,
			new ImmutableDateTime('2026-11-20'),
			null,
			'CSSZ listopad',
			$now,
			$account,
		);
		$novSocial->setMainTag($socialTag);
		$this->entityManager->persist($octSocial);
		$this->entityManager->persist($novSocial);

		$this->entityManager->flush();

		// Blank 0h March row must be excluded from forecast average (9 non-zero months @ 100k -> Nov 100k, total 1.1M)
		$novCheck = new ImmutableDateTime('2026-11-15 10:00:00');
		$snapshotNov = $this->reportBuilder->build(new TaxYearReport($this->owner, 2026, $novCheck, $novCheck));
		self::assertSame(9, $snapshotNov->businessIncome->autoForecastMonthsUsed);
		self::assertSame(1_100_000, $snapshotNov->businessIncome->autoForecastAnnualIncomeCzk);
		self::assertNotEmpty(array_filter(
			$snapshotNov->warnings,
			static fn (string $w): bool => str_contains($w, 'má 0 hodin'),
		));

		$report2026 = new TaxYearReport($this->owner, 2026, $now, $now);
		$snapshot2026 = $this->reportBuilder->build($report2026);
		$report2026->complete($snapshot2026, $now);
		$this->entityManager->persist($report2026);
		$this->entityManager->flush();

		self::assertSame(9, $snapshot2026->businessIncome->autoForecastMonthsUsed);
		self::assertNotEmpty(array_filter(
			$snapshot2026->warnings,
			static fn (string $w): bool => str_contains($w, 'má 0 hodin'),
		));

		// VZP: 11 payments paid on 5th of Feb..Dec (Dec payment had NULL transactionDate and amount 3 500 Kč)
		// Remaining on Dec 15 = 1 month (December VZP due Jan 8, 2027) * 3 500 Kč
		self::assertSame(10 * 3_306 + 3_500, $snapshot2026->healthInsuranceSummary->paidAdvancesCzk);
		self::assertSame(3_500, $snapshot2026->healthInsuranceSummary->expectedRemainingAdvancesCzk);

		// OSSZ: November payment (6 500 Kč, NULL transactionDate) must be selected as last paid advance for December
		self::assertSame(12_500, $snapshot2026->socialInsuranceSummary->paidAdvancesCzk);
		self::assertSame(6_500, $snapshot2026->socialInsuranceSummary->expectedRemainingAdvancesCzk);

		// 6. January 1, 2027 with 0 payments: must expect all 12 months (not 11) and warn about open previous year 2026
		$janFirst2027 = new ImmutableDateTime('2027-01-01 08:00:00');
		$report2027 = new TaxYearReport($this->owner, 2027, $janFirst2027, $janFirst2027);
		$snapshot2027 = $this->reportBuilder->build($report2027);

		self::assertSame(
			12 * max($snapshot2026->healthInsuranceDetail->nextMonthlyAdvanceCzk, 3_488),
			$snapshot2027->healthInsuranceSummary->expectedRemainingAdvancesCzk,
		);
		self::assertNotEmpty(array_filter(
			$snapshot2027->warnings,
			static fn (string $w): bool => str_contains($w, 'neuzavřeného odhadu za rok 2026'),
		));
	}

}
