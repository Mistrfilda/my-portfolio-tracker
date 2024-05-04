<?php

declare(strict_types = 1);

namespace App\Cash\WorkMonthlyIncome\UI;

use App\Asset\Price\SummaryPrice;
use App\Cash\WorkMonthlyIncome\WorkMonthlyIncomeRepository;
use App\Currency\CurrencyEnum;
use App\UI\Base\BaseSysadminPresenter;
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
	];

	#[Persistent]
	public int $selectedYear = self::DEFAULT_YEAR;

	public function __construct(
		private WorkMonthlyIncomeRepository $workMonthlyIncomeRepository,
		private DatetimeFactory $datetimeFactory,
		private CzechHolidayService $czechHolidayService,
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

		$currentMonthWorkIncome = $this->workMonthlyIncomeRepository->findByYearAndMonth(
			$now->getYear(),
			$now->getMonth(),
		);
		$this->template->currentMonthWorkIncome = $currentMonthWorkIncome;

		$this->template->totalSummaryPrice = $totalSummaryPrice;
		$this->template->yearOptions = [2023, 2024, 2025];
		$this->template->selectedYear = $this->selectedYear;

		$lastDayOfMonth = $now->modify('last day of this month');
		$daysTillEndOfMonth = $lastDayOfMonth->getDay() - $now->getDay();

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

		$this->template->daysTillEndOfMonth = $lastDayOfMonth->getDay() - $now->getDay();
		$this->template->workingDaysTillEndOfMonth = $workingDaysTillEndOfMonth;
		$goals = [];
		if ($currentMonthWorkIncome !== null) {
			foreach (self::MONEY_GOALS as $moneyGoal) {
				$remainingAmount = $moneyGoal - $currentMonthWorkIncome->getSummaryPrice()->getPrice();
				$remainingHours = $remainingAmount < 0 ? 0 : $remainingAmount / $currentMonthWorkIncome->getHourlyRate();

				$workDaysAverage = $remainingHours === 0 ? 0 : $remainingHours / $workingDaysTillEndOfMonth;
				$allDaysAverage = $remainingHours === 0 ? 0 : $remainingHours / $daysTillEndOfMonth;

				$goals[] = [
					'amount' => $moneyGoal . ' Kč',
					'remainingHours' => round($remainingHours, 1),
					'workDaysAverage' => round($workDaysAverage, 1),
					'allDaysAverage' => round($allDaysAverage, 1),
				];
			}
		}

		$this->template->goals = $goals;
	}

	public function handleSetYear(int $selectedYear = self::DEFAULT_YEAR): void
	{
		$this->selectedYear = $selectedYear;
		$this->invalidatePage();
	}

}
