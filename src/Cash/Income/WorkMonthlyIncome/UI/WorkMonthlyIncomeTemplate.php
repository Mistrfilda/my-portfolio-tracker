<?php

declare(strict_types = 1);

namespace App\Cash\Income\WorkMonthlyIncome\UI;

use App\Asset\Price\SummaryPrice;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncome;
use App\Goal\PortfolioGoal;
use App\UI\Base\BaseAdminPresenterTemplate;

class WorkMonthlyIncomeTemplate extends BaseAdminPresenterTemplate
{

	public WorkMonthlyIncome|null $currentMonthWorkIncome;

	/** @var array<WorkMonthlyIncome> */
	public array $workMonthlyIncomes;

	/** @var array<int, int> */
	public array $yearOptions;

	public int $selectedYear;

	public SummaryPrice $totalSummaryPrice;

	public int $daysTillEndOfMonth;

	public int $workingDaysTillEndOfMonth;

	/** @var array<int, array{amount: string, remainingHours: float|int, workDaysAverage: float|int|null, allDaysAverage: float|int|null}> $goals */
	public array $goals;

	/** @var array<int, array{amount: string, hour: int}> $hours */
	public array $hours;

	public PortfolioGoal|null $activeIncomeGoal;

}
