<?php

declare(strict_types = 1);

namespace App\Test\Unit\Tax;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\JobRequest\JobRequestFacade;
use App\Tax\Calculator\BusinessIncomeCalculator;
use App\Tax\Calculator\DividendTaxCalculator;
use App\Tax\Calculator\HealthInsuranceCalculator;
use App\Tax\Calculator\IncomeTaxCalculator;
use App\Tax\Calculator\SocialInsuranceCalculator;
use App\Tax\Calculator\StockSalesCalculator;
use App\Tax\DTO\TaxYearReportSnapshotDTO;
use App\Tax\Rules\TaxDividendModeEnum;
use App\Tax\Rules\TaxExpenseFlatRateEnum;
use App\Tax\Rules\TaxTreatyRateProvider;
use App\Tax\Rules\TaxYearRulesProvider;
use App\Tax\TaxYearProfile;
use App\Tax\TaxYearProfileFacade;
use App\Tax\TaxYearProfileRepository;
use App\Tax\TaxYearReport;
use App\Tax\TaxYearReportBuilder;
use App\Tax\TaxYearReportFacade;
use App\Tax\TaxYearReportRepository;
use App\Tax\TaxYearReportStatusEnum;
use App\Tax\UI\TaxYearDonationFormFactory;
use App\Test\UpdatedTestCase;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Psr\Log\NullLogger;

class TaxYearReportFacadeTest extends UpdatedTestCase
{

	public function testEnqueueOpenYearRefreshDeduplicatesAndSkipsClosedOrUntrackedYears(): void
	{
		$now = new ImmutableDateTime('2026-11-24 12:00:00');
		$owner = $this->createOwner($now);

		// 1. Untracked year (no previous report) -> does not enqueue
		$jobRequestFacade = $this->createMock(JobRequestFacade::class);
		$jobRequestFacade->expects(self::never())->method('addTaxYearReportProcessToQueue');

		$reportRepo = $this->createStub(TaxYearReportRepository::class);
		$reportRepo->method('findLatestForYear')->willReturn(null);

		$profileRepo = $this->createStub(TaxYearProfileRepository::class);
		$profileRepo->method('findByYear')->willReturn(null);

		$facade = $this->createFacade($reportRepo, $profileRepo, $jobRequestFacade, $now);
		self::assertNull($facade->enqueueOpenYearRefresh(2026, $owner));

		// 2. Closed year -> does not enqueue
		$closedProfile = new TaxYearProfile($owner, 2025, $now);
		$closedProfile->updateSettings(
			expenseFlatRate: $closedProfile->getExpenseFlatRate(),
			activeMonthsHealth: 12,
			activeMonthsSocial: 12,
			dividendMode: $closedProfile->getDividendMode(),
			includePreviousDecemberWorkIncome: true,
			manualBusinessIncomeAdjustmentCzk: 0,
			manualBusinessIncomeAdjustmentNote: null,
			reserveTargetDate: null,
			expectedMonthlyHealthAdvanceCzk: null,
			expectedMonthlySocialAdvanceCzk: null,
			expectedRemainingIncomeTaxAdvancesCzk: null,
			confirmedIncomeTaxAdvancesCzk: null,
			confirmedHealthAdvancesCzk: null,
			confirmedSocialAdvancesCzk: null,
			pensionAndLifeDeductionCzk: 0,
			mortgageInterestDeductionCzk: 0,
			otherDeductionsCzk: 0,
			otherTaxCreditsCzk: 0,
			isClosed: true,
			now: $now,
		);

		$jobRequestFacadeClosed = $this->createMock(JobRequestFacade::class);
		$jobRequestFacadeClosed->expects(self::never())->method('addTaxYearReportProcessToQueue');

		$profileRepoClosed = $this->createStub(TaxYearProfileRepository::class);
		$profileRepoClosed->method('findByYear')->willReturn($closedProfile);

		$facadeClosed = $this->createFacade($reportRepo, $profileRepoClosed, $jobRequestFacadeClosed, $now);
		self::assertNull($facadeClosed->enqueueOpenYearRefresh(2025, $owner));

		// 3. Existing QUEUED pending report -> deduplicates and returns existing without enqueuing
		$existingQueued = new TaxYearReport($owner, 2026, $now, $now);

		$jobRequestFacadeDedup = $this->createMock(JobRequestFacade::class);
		$jobRequestFacadeDedup->expects(self::never())->method('addTaxYearReportProcessToQueue');

		$reportRepoDedup = $this->createStub(TaxYearReportRepository::class);
		$reportRepoDedup->method('findLatestForYear')->willReturn($existingQueued);
		$reportRepoDedup->method('findLatestPendingForYear')->willReturn($existingQueued);

		$facadeDedup = $this->createFacade($reportRepoDedup, $profileRepo, $jobRequestFacadeDedup, $now);
		self::assertSame($existingQueued, $facadeDedup->enqueueOpenYearRefresh(2026, $owner));

		// 4. Completed previous report and no pending -> enqueues exactly once
		$previousCompleted = new TaxYearReport($owner, 2026, $now, $now);

		$jobRequestFacadeEnqueue = $this->createMock(JobRequestFacade::class);
		$jobRequestFacadeEnqueue->expects(self::once())->method('addTaxYearReportProcessToQueue');

		$reportRepoEnqueue = $this->createStub(TaxYearReportRepository::class);
		$reportRepoEnqueue->method('findLatestForYear')->willReturn($previousCompleted);
		$reportRepoEnqueue->method('findLatestPendingForYear')->willReturn(null);

		$facadeEnqueue = $this->createFacade($reportRepoEnqueue, $profileRepo, $jobRequestFacadeEnqueue, $now);
		$enqueued = $facadeEnqueue->enqueueOpenYearRefresh(2026, $owner);
		self::assertNotNull($enqueued);
		self::assertSame(TaxYearReportStatusEnum::QUEUED, $enqueued->getStatus());
	}

	public function testStaleProcessingReportCanBeReclaimedByProcessAndRetry(): void
	{
		$startedAt = new ImmutableDateTime('2026-11-24 12:00:00');
		$afterFiveMinutes = new ImmutableDateTime('2026-11-24 12:05:00');
		$afterElevenMinutes = new ImmutableDateTime('2026-11-24 12:11:00');
		$owner = $this->createOwner($startedAt);

		$report = new TaxYearReport($owner, 2026, $startedAt, $startedAt);
		$report->markProcessing($startedAt);

		self::assertFalse($report->isStaleProcessing($afterFiveMinutes));
		self::assertTrue($report->isPending($afterFiveMinutes));
		self::assertFalse($report->canRetry($afterFiveMinutes));

		self::assertTrue($report->isStaleProcessing($afterElevenMinutes));
		self::assertFalse($report->isPending($afterElevenMinutes));
		self::assertTrue($report->canRetry($afterElevenMinutes));

		// Retry after 5 minutes does nothing
		$jobRequestNoOp = $this->createMock(JobRequestFacade::class);
		$jobRequestNoOp->expects(self::never())->method('addTaxYearReportProcessToQueue');
		$reportRepo = $this->createStub(TaxYearReportRepository::class);
		$reportRepo->method('getById')->willReturn($report);
		$reportRepo->method('getByIdForProcessing')->willReturn($report);

		$facadeBeforeTimeout = $this->createFacade(
			$reportRepo,
			$this->createStub(TaxYearProfileRepository::class),
			$jobRequestNoOp,
			$afterFiveMinutes,
		);
		$facadeBeforeTimeout->retry($report->getId()->toString());
		self::assertSame(TaxYearReportStatusEnum::PROCESSING, $report->getStatus());

		// Retry after 11 minutes re-queues the stale report
		$jobRequestRetry = $this->createMock(JobRequestFacade::class);
		$jobRequestRetry->expects(self::once())
			->method('addTaxYearReportProcessToQueue')
			->with($report->getId()->toString());

		$facadeAfterTimeout = $this->createFacade(
			$reportRepo,
			$this->createStub(TaxYearProfileRepository::class),
			$jobRequestRetry,
			$afterElevenMinutes,
		);
		$facadeAfterTimeout->retry($report->getId()->toString());
		self::assertSame(TaxYearReportStatusEnum::QUEUED, $report->getStatus());

		// Process reclaims a stale PROCESSING report directly without manual retry
		$report->markProcessing($startedAt);
		$builder = $this->createMock(TaxYearReportBuilder::class);
		$builder->expects(self::once())
			->method('build')
			->willReturn($this->createEmptySnapshot());

		$facadeProcess = $this->createFacade(
			$reportRepo,
			$this->createStub(TaxYearProfileRepository::class),
			$this->createStub(JobRequestFacade::class),
			$afterElevenMinutes,
			$builder,
		);
		$facadeProcess->process($report->getId()->toString());
		self::assertSame(TaxYearReportStatusEnum::COMPLETED, $report->getStatus());
	}

	public function testDonationFormAllowsEmptyDateAndRejectsWrongYear(): void
	{
		$adminFormFactory = $this->createStub(AdminFormFactory::class);
		$adminFormFactory->method('create')->willReturnCallback(static fn (): AdminForm => new AdminForm());

		$profileFacade = $this->createStub(TaxYearProfileFacade::class);
		$factory = new TaxYearDonationFormFactory($adminFormFactory, $profileFacade);

		// 1. Empty optional date is valid and yields null
		$formEmptyDate = $factory->create(2025, static function (): void {
		});
		$formEmptyDate->setValues([
			'recipientName' => 'Darujme.cz',
			'amountCzk' => 2_500,
			'donatedAt' => '',
			'note' => '',
		]);
		$formEmptyDate->validate();
		self::assertSame([], $formEmptyDate->getErrors());
		self::assertNull($formEmptyDate['donatedAt']->getValue());

		// 2. Date outside the selected tax year is rejected
		$formWrongYear = $factory->create(2025, static function (): void {
		});
		$formWrongYear->setValues([
			'recipientName' => 'Donio.cz',
			'amountCzk' => 2_500,
			'donatedAt' => '2026-01-10',
			'note' => '',
		]);
		$formWrongYear->validate();
		self::assertNotSame([], $formWrongYear->getErrors());

		// 3. Date inside the selected tax year is accepted as ImmutableDateTime
		$formValidDate = $factory->create(2025, static function (): void {
		});
		$formValidDate->setValues([
			'recipientName' => 'Darujme.cz',
			'amountCzk' => 2_500,
			'donatedAt' => '2025-11-10',
			'note' => 'Potvrzeni 1',
		]);
		$formValidDate->validate();
		self::assertSame([], $formValidDate->getErrors());
		self::assertInstanceOf(ImmutableDateTime::class, $formValidDate['donatedAt']->getValue());
		self::assertSame('2025-11-10', $formValidDate['donatedAt']->getValue()->format('Y-m-d'));
	}

	private function createEmptySnapshot(): TaxYearReportSnapshotDTO
	{
		$rulesProvider = new TaxYearRulesProvider();
		$rules2026 = $rulesProvider->getForYear(2026);
		$rules2027 = $rulesProvider->getForYear(2027);
		$businessSection = (new BusinessIncomeCalculator())->calculate(
			items: [],
			flatRate: TaxExpenseFlatRateEnum::RATE_60,
			manualAdjustmentCzk: 0,
			manualAdjustmentNote: null,
			autoForecastPeriodDescription: 'test',
			autoForecastMonthsUsed: 0,
			customForecastAnnualIncomeCzk: null,
			useCustomForecast: false,
			isOpenYear: true,
		);
		$stockSales = (new StockSalesCalculator())->calculate([], $rules2026);
		$dividendCalculator = new DividendTaxCalculator(new TaxTreatyRateProvider());
		$dividends = $dividendCalculator->calculate([], TaxDividendModeEnum::STANDARD_SECTION_8);
		$health = (new HealthInsuranceCalculator())->calculate(0, 12, $rules2026, $rules2027, 0, 0, 'test');
		$social = (new SocialInsuranceCalculator())->calculate(0, 12, $rules2026, $rules2027, 0, 0, 0, 'test');
		$incomeTax = (new IncomeTaxCalculator($dividendCalculator))->calculate(
			0,
			0,
			$dividends,
			TaxDividendModeEnum::STANDARD_SECTION_8,
			0,
			0,
			0,
			0,
			0,
			$rules2026,
			0,
			0,
			'test',
		);

		return new TaxYearReportSnapshotDTO(
			year: 2026,
			asOfDate: '2026-11-24',
			isOpenYear: true,
			isClosed: false,
			isEstimatedRules: false,
			reserveTargetDate: null,
			businessIncome: $businessSection,
			stockSales: $stockSales,
			dividends: $incomeTax['dividends'],
			healthInsuranceDetail: $health['detail'],
			socialInsuranceDetail: $social['detail'],
			incomeTaxDetail: $incomeTax['detail'],
			taxOfficeSummary: $incomeTax['summary'],
			healthInsuranceSummary: $health['summary'],
			socialInsuranceSummary: $social['summary'],
			totalAnnualLiabilityCzk: 0,
			totalPaidAdvancesCzk: 0,
			totalExpectedRemainingAdvancesCzk: 0,
			totalReserveToPrepareCzk: 0,
			totalExpectedOverpaymentsCzk: 0,
			totalRemainingCashflowThisYearAndSettlementCzk: 0,
			donations: [],
			payments: [],
			warnings: [],
		);
	}

	private function createOwner(ImmutableDateTime $now): AppAdmin
	{
		return new AppAdmin('tester', 'Tester', 'tester@example.test', 'unused', $now, false, false);
	}

	private function createFacade(
		TaxYearReportRepository $reportRepository,
		TaxYearProfileRepository $profileRepository,
		JobRequestFacade $jobRequestFacade,
		ImmutableDateTime $now,
		TaxYearReportBuilder|null $builder = null,
	): TaxYearReportFacade
	{
		$datetimeFactory = $this->createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);

		return new TaxYearReportFacade(
			$reportRepository,
			$profileRepository,
			$builder ?? $this->createStub(TaxYearReportBuilder::class),
			$jobRequestFacade,
			$this->createStub(EntityManagerInterface::class),
			$datetimeFactory,
			new NullLogger(),
			$this->createStub(CurrentAppAdminGetter::class),
		);
	}

}
