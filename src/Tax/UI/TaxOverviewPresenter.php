<?php

declare(strict_types = 1);

namespace App\Tax\UI;

use App\Tax\Calculator\TaxYearCalculationOrchestrator;
use App\Tax\DTO\TaxScenarioComparisonDTO;
use App\Tax\DTO\TaxYearReportSnapshotDTO;
use App\Tax\Rules\TaxYearRulesProvider;
use App\Tax\TaxYearDonationRepository;
use App\Tax\TaxYearProfile;
use App\Tax\TaxYearProfileFacade;
use App\Tax\TaxYearReport;
use App\Tax\TaxYearReportFacade;
use App\Tax\TaxYearReportRepository;
use App\UI\Base\BaseAdminPresenter;
use App\UI\Control\Form\AdminForm;
use App\UI\FlashMessage\FlashMessageType;
use DateTimeImmutable;
use Mistrfilda\Datetime\DatetimeFactory;

/**
 * @property-read TaxOverviewTemplate $template
 */
class TaxOverviewPresenter extends BaseAdminPresenter
{

	private int $selectedYear = 2025;

	private TaxYearProfile|null $profile = null;

	private TaxYearReport|null $latestReport = null;

	private TaxYearReport|null $completedReport = null;

	private TaxYearReportSnapshotDTO|null $snapshot = null;

	private TaxScenarioComparisonDTO|null $scenarioComparison = null;

	private int|null $scenarioIncomeCzk = null;

	public function __construct(
		private TaxYearProfileFacade $profileFacade,
		private TaxYearDonationRepository $donationRepository,
		private TaxYearReportRepository $reportRepository,
		private TaxYearReportFacade $reportFacade,
		private TaxYearRulesProvider $rulesProvider,
		private TaxYearCalculationOrchestrator $calculationOrchestrator,
		private TaxForecastFormFactory $forecastFormFactory,
		private TaxYearDonationFormFactory $donationFormFactory,
		private TaxYearProfileFormFactory $profileFormFactory,
		private DatetimeFactory $datetimeFactory,
	)
	{
		parent::__construct();
	}

	public function actionDefault(int|null $year = null, int|null $scenarioIncomeCzk = null): void
	{
		$currentYear = $this->datetimeFactory->createNow()->getYear();
		$this->selectedYear = $year ?? $currentYear;
		if ($this->selectedYear < 2020 || $this->selectedYear > 2100) {
			$this->selectedYear = $currentYear;
		}

		$this->profile = $this->profileFacade->getOrCreate($this->selectedYear);
		$this->latestReport = $this->reportRepository->findLatestForYear($this->selectedYear);
		$this->completedReport = $this->reportRepository->findLatestCompletedForYear($this->selectedYear);
		$this->snapshot = $this->completedReport?->getSnapshot();
		$this->scenarioIncomeCzk = $scenarioIncomeCzk !== null ? max(0, $scenarioIncomeCzk) : null;

		if ($this->snapshot !== null && $this->scenarioIncomeCzk !== null) {
			$this->scenarioComparison = $this->calculationOrchestrator->recalculateScenario(
				$this->snapshot,
				$this->scenarioIncomeCzk,
			);
		}
	}

	public function renderDefault(int|null $year = null, int|null $scenarioIncomeCzk = null): void
	{
		if ($this->profile === null) {
			$this->error('Tax year profile is not initialized.');
		}

		$availableYears = $this->rulesProvider->getSupportedYears();
		$currentYear = $this->datetimeFactory->createNow()->getYear();
		if (!in_array($currentYear, $availableYears, true)) {
			$availableYears[] = $currentYear;
		}

		if (!in_array($this->selectedYear, $availableYears, true)) {
			$availableYears[] = $this->selectedYear;
		}

		sort($availableYears);

		$bankDonations = [];
		$bankDonationsTotalCzk = 0;
		if ($this->snapshot !== null) {
			foreach ($this->snapshot->donations as $donation) {
				if ($donation->source === 'bank_expense') {
					$bankDonations[] = $donation;
					$bankDonationsTotalCzk += $donation->amountCzk;
				}
			}
		}

		$now = $this->datetimeFactory->createNow();
		$yearRules = $this->rulesProvider->getForYear($this->selectedYear);

		$this->template->heading = sprintf('Daně a odvody %d', $this->selectedYear);
		$this->template->selectedYear = $this->selectedYear;
		$this->template->availableYears = $availableYears;
		$this->template->profile = $this->profile;
		$this->template->manualDonations = $this->donationRepository->findByProfile($this->profile);
		$this->template->bankDonations = $bankDonations;
		$this->template->bankDonationsTotalCzk = $bankDonationsTotalCzk;
		$this->template->latestReport = $this->latestReport;
		$this->template->latestReportIsPending = $this->latestReport !== null && $this->latestReport->isPending($now);
		$this->template->latestReportCanRetry = $this->latestReport !== null && $this->latestReport->canRetry($now);
		$this->template->completedReport = $this->completedReport;
		$this->template->snapshot = $this->snapshot;
		$this->template->scenarioComparison = $this->scenarioComparison;
		$this->template->scenarioIncomeCzk = $this->scenarioIncomeCzk;
		$this->template->donationMaxBasePercentage = (int) round($yearRules->giftMaxBasePercentage * 100);
		$this->template->asOfDateFormatted = $this->snapshot !== null
			? $this->formatIsoDateCzech($this->snapshot->asOfDate)
			: null;
		$this->template->reserveTargetDateFormatted = $this->snapshot?->reserveTargetDate !== null
			? $this->formatIsoDateCzech($this->snapshot->reserveTargetDate)
			: null;
	}

	public function handleGenerateReport(int $year): void
	{
		$this->reportFacade->enqueue($year);
		$this->flashMessage(
			sprintf('Přehled daní a odvodů za rok %d byl zařazen do fronty ke zpracování.', $year),
			FlashMessageType::SUCCESS,
		);
		$this->redirect('default', ['year' => $year]);
	}

	public function handleRetryReport(string $reportId, int $year): void
	{
		$this->reportFacade->retry($reportId);
		$this->flashMessage('Přepočet daňového přehledu byl znovu zařazen do fronty.', FlashMessageType::SUCCESS);
		$this->redirect('default', ['year' => $year]);
	}

	public function handleApplyScenario(int $year, int $scenarioIncomeCzk): void
	{
		$this->profileFacade->updateForecast($year, true, $scenarioIncomeCzk);
		$this->reportFacade->enqueue($year);
		$this->flashMessage(
			sprintf(
				'Vlastní celoroční odhad (%s Kč) byl uložen a přehled za rok %d byl zařazen k přepočtu.',
				number_format($scenarioIncomeCzk, 0, ',', ' '),
				$year,
			),
			FlashMessageType::SUCCESS,
		);
		$this->redirect('default', ['year' => $year]);
	}

	public function handleResetForecast(int $year): void
	{
		$this->profileFacade->updateForecast($year, false, null);
		$this->reportFacade->enqueue($year);
		$this->flashMessage(
			'Obnoven automatický odhad pracovního příjmu a zařazen přepočet přehledu.',
			FlashMessageType::SUCCESS,
		);
		$this->redirect('default', ['year' => $year]);
	}

	public function handleDeleteDonation(string $donationId, int $year): void
	{
		$this->profileFacade->deleteDonation($donationId);
		$this->reportFacade->enqueue($year);
		$this->flashMessage('Dar byl odstraněn a přehled byl zařazen k přepočtu.', FlashMessageType::SUCCESS);
		$this->redirect('default', ['year' => $year]);
	}

	protected function createComponentTaxForecastForm(): AdminForm
	{
		$initialValue = $this->scenarioIncomeCzk
			?? $this->profile?->getCustomForecastAnnualIncomeCzk()
			?? $this->snapshot?->businessIncome->autoForecastAnnualIncomeCzk;

		return $this->forecastFormFactory->create(
			$initialValue,
			function (int $annualIncomeCzk): void {
				$this->redirect('default#tax-scenario', [
					'year' => $this->selectedYear,
					'scenarioIncomeCzk' => $annualIncomeCzk,
				]);
			},
		);
	}

	protected function createComponentTaxYearDonationForm(): AdminForm
	{
		return $this->donationFormFactory->create(
			$this->selectedYear,
			function (): void {
				$this->reportFacade->enqueue($this->selectedYear);
				$this->flashMessage('Dar byl přidán a přehled byl zařazen k přepočtu.', FlashMessageType::SUCCESS);
				$this->redirect('default', ['year' => $this->selectedYear]);
			},
		);
	}

	protected function createComponentTaxYearProfileForm(): AdminForm
	{
		if ($this->profile === null) {
			$this->error('Tax year profile is not initialized.');
		}

		return $this->profileFormFactory->create(
			$this->profile,
			function (): void {
				$this->reportFacade->enqueue($this->selectedYear);
				$this->flashMessage(
					sprintf('Nastavení roku %d bylo uloženo a přehled byl zařazen k přepočtu.', $this->selectedYear),
					FlashMessageType::SUCCESS,
				);
				$this->redirect('default', ['year' => $this->selectedYear]);
			},
		);
	}

	private function formatIsoDateCzech(string $isoDate): string
	{
		$parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $isoDate);
		if ($parsed === false) {
			return $isoDate;
		}

		return $parsed->format('j. n. Y');
	}

}
