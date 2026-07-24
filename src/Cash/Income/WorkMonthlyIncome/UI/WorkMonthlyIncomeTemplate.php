<?php

declare(strict_types = 1);

namespace App\Cash\Income\WorkMonthlyIncome\UI;

use App\Asset\Price\SummaryPrice;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncome;
use App\Goal\PortfolioGoal;
use App\UI\Base\BaseAdminPresenterTemplate;

class WorkMonthlyIncomeTemplate extends BaseAdminPresenterTemplate
{

	public WorkMonthlyIncome $currentMonthWorkIncome;

	/** @var array<WorkMonthlyIncome> */
	public array $workMonthlyIncomes;

	/** @var array<int, int> */
	public array $yearOptions;

	public int $selectedYear;

	public SummaryPrice $totalSummaryPrice;

	public int $daysTillEndOfMonth;

	public int $workingDaysTillEndOfMonth;

	/**
	 * @var array<int, array{
	 *     amount: string,
	 *     requiredHours: float,
	 *     remainingHours: float,
	 *     workDaysAverage: float|null,
	 *     allDaysAverage: float|null,
	 *     reached: bool
	 * }>
	 */
	public array $goals;

	/**
	 * @var array<int, array{
	 *     amount: string,
	 *     requiredHours: float,
	 *     remainingHours: float,
	 *     workDaysAverage: float|null,
	 *     allDaysAverage: float|null,
	 *     reached: bool
	 * }>
	 */
	public array $visibleGoals;

	/**
	 * @var array{
	 *     amount: string,
	 *     requiredHours: float,
	 *     remainingHours: float,
	 *     workDaysAverage: float|null,
	 *     allDaysAverage: float|null,
	 *     reached: bool
	 * }|null
	 */
	public array|null $nextGoal;

	/** @var array<int, array{amount: string, hour: int}> $hours */
	public array $hours;

	public PortfolioGoal|null $activeIncomeGoal;

	public float|null $activeGoalProgressPercentage;

	public float|null $activeGoalProgressBarPercentage;

	public float|null $activeGoalDifference;

}
