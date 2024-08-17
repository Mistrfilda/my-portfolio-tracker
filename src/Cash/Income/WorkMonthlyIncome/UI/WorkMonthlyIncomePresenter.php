<?php

declare(strict_types = 1);

namespace App\Cash\Income\WorkMonthlyIncome\UI;

use App\Asset\Price\SummaryPrice;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncomeFacade;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncomeRepository;
use App\Currency\CurrencyEnum;
use App\UI\Base\BaseSysadminPresenter;
use App\UI\Filter\CurrencyFilter;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Holiday\CzechHolidayService;
use Nette\Application\Attributes\Persistent;

/**
 * @property-read WorkMonthlyIncomeTemplate $template
 */
class WorkMonthlyIncomePresenter extends BaseSysadminPresenter
{

	public const DEFAULT_YEAR = 2024;

	public const MONEY_GOALS = [
		50000,
		60000,
		65000,
		70000,
		75000,
		78000,
		81000,
		84000,
		87000,
		90000,
		93000,
	];

	public const HOURS = [
		10,
		20,
		30,
		40,
		50,
		60,
		70,
		80,
		90,
		100,
		105,
		110,
		115,
		120,
		125,
		130,
	];

	#[Persistent]
	public int $selectedYear = self::DEFAULT_YEAR;

	public function __construct(
		private WorkMonthlyIncomeRepository $workMonthlyIncomeRepository,
		private DatetimeFactory $datetimeFactory,
		private CzechHolidayService $czechHolidayService,
		private WorkMonthlyIncomeFacade $workMonthlyIncomeFacade,
	)
	{
		parent::__construct();
	}

	public function renderDefault(int $selectedYear = self::DEFAULT_YEAR): void
	{
		$now = $this->datetimeFactory->createNow();
		$this->template->heading = 'Přehled příjmů';
		$incomeRows = $this->workMonthlyIncomeRepository->findAll($selectedYear);
		$this->template->workMonthlyIncomes = $incomeRows;
		$totalSummaryPrice = new SummaryPrice(CurrencyEnum::CZK);
		foreach ($incomeRows as $incomeRow) {
			$totalSummaryPrice->addSummaryPrice($incomeRow->getSummaryPrice());
		}

		$currentMonthWorkIncome = $this->workMonthlyIncomeRepository->getByYearAndMonth(
			$now->getYear(),
			$now->getMonth(),
		);

		if ($currentMonthWorkIncome === null) {
			$currentMonthWorkIncome = $this->workMonthlyIncomeFacade->createBlank(
				$now->getYear(),
				$now->getMonth(),
			);
		}

		$this->template->currentMonthWorkIncome = $currentMonthWorkIncome;

		$this->template->totalSummaryPrice = $totalSummaryPrice;
		$this->template->yearOptions = [2023, 2024, 2025];
		$this->template->selectedYear = $this->selectedYear;

		$lastDayOfMonth = $now->modify('last day of this month');
		$daysTillEndOfMonth = $lastDayOfMonth->getDay() - $now->getDay() + 1;

		if (in_array($now->format('D'), ['Sun', 'Sat'], true) || $this->czechHolidayService->isDateTimeHoliday($now)) {
			$workingDaysTillEndOfMonth = 0;
		} else {
			$workingDaysTillEndOfMonth = 1;
		}

		$firstDayOfMonth = $now->setDate($now->getYear(), $now->getMonth(), 1);
		for ($i = $now->getDay(); $i < $lastDayOfMonth->getDay(); $i++) {
			$date = $firstDayOfMonth->addDaysToDatetime($i);
			if (
				in_array($date->format('D'), ['Sun', 'Sat'], true)
				|| $this->czechHolidayService->isDateTimeHoliday($date)
			) {
				continue;
			}

			$workingDaysTillEndOfMonth++;
		}

		$this->template->daysTillEndOfMonth = $daysTillEndOfMonth;
		$this->template->workingDaysTillEndOfMonth = $workingDaysTillEndOfMonth;
		$goals = [];
		$hours = [];

		foreach (self::MONEY_GOALS as $moneyGoal) {
			$remainingAmount = $moneyGoal - $currentMonthWorkIncome->getSummaryPrice()->getPrice();
			$remainingHours = $remainingAmount < 0 ? 0 : $remainingAmount / $currentMonthWorkIncome->getHourlyRate();

			$workDaysAverage = null;
			if ($workingDaysTillEndOfMonth !== 0) {
				$workDaysAverage = $remainingHours === 0 ? 0 : $remainingHours / $workingDaysTillEndOfMonth;
			}

			$allDaysAverage = null;
			if ($daysTillEndOfMonth !== 0) {
				$allDaysAverage = $remainingHours === 0 ? 0 : $remainingHours / $daysTillEndOfMonth;
			}

			$requiredHours = $moneyGoal / $currentMonthWorkIncome->getHourlyRate();

			$goals[] = [
				'amount' => CurrencyFilter::format($moneyGoal, CurrencyEnum::CZK),
				'requiredHours' => round($requiredHours, 1),
				'remainingHours' => round($remainingHours, 1),
				'workDaysAverage' => $workDaysAverage === null ? null : round($workDaysAverage, 1),
				'allDaysAverage' => $allDaysAverage === null ? null : round($allDaysAverage, 1),
			];
		}

		foreach (self::HOURS as $hour) {
			$hours[] = [
				'hour' => $hour,
				'amount' => CurrencyFilter::format(
					$hour * $currentMonthWorkIncome->getHourlyRate(),
					CurrencyEnum::CZK,
				),
			];
		}

		$this->template->hours = $hours;
		$this->template->goals = $goals;
	}

	public function handleSetYear(int $selectedYear = self::DEFAULT_YEAR): void
	{
		$this->selectedYear = $selectedYear;
		$this->invalidatePage();
	}

}
