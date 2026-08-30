<?php

declare(strict_types = 1);

namespace App\FinancialIndependence\UI;

use App\FinancialIndependence\FinancialIndependenceCalculationInput;
use App\FinancialIndependence\FinancialIndependenceCalculationResult;
use App\FinancialIndependence\FinancialIndependenceCalculator;
use App\FinancialIndependence\FinancialIndependenceDefaults;
use App\FinancialIndependence\FinancialIndependenceDefaultsProvider;
use App\UI\Base\BaseAdminPresenter;
use App\UI\Control\Chart\ChartControl;
use App\UI\Control\Chart\ChartControlFactory;
use App\UI\Control\Chart\ChartType;
use App\UI\Control\Form\AdminForm;
use App\UI\FlashMessage\FlashMessageType;
use InvalidArgumentException;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

/**
 * @property-read FinancialIndependenceTemplate $template
 */
class FinancialIndependencePresenter extends BaseAdminPresenter
{

	private const array WITHDRAWAL_SCENARIO_RATES = [3.0, 3.5, 4.0];

	private FinancialIndependenceDefaults|null $defaults = null;

	private FinancialIndependenceCalculationInput|null $calculationInput = null;

	private FinancialIndependenceCalculationResult|null $result = null;

	private ImmutableDateTime|null $projectionStartDate = null;

	private int|null $projectionHorizonMonths = null;

	public function __construct(
		private readonly FinancialIndependenceDefaultsProvider $defaultsProvider,
		private readonly FinancialIndependenceCalculator $calculator,
		private readonly FinancialIndependenceFormFactory $formFactory,
		private readonly ChartControlFactory $chartControlFactory,
		private readonly DatetimeFactory $datetimeFactory,
	)
	{
		parent::__construct();
	}

	public function actionDefault(
		float|null $currentPortfolioValueCzk = null,
		float|null $targetMonthlyExpensesCzk = null,
		float|null $monthlyContributionCzk = null,
		float|null $annualNominalReturnPercentage = null,
		float|null $annualInflationPercentage = null,
		float|null $annualCostPercentage = null,
		float|null $withdrawalRatePercentage = null,
		int|null $birthYear = null,
		int|null $plannedLifespanAge = null,
	): void
	{
		$this->defaults = $this->defaultsProvider->provide();
		$trackerInput = $this->defaults->calculationInput;
		$this->calculationInput = new FinancialIndependenceCalculationInput(
			currentPortfolioValueCzk: $currentPortfolioValueCzk ?? $trackerInput->currentPortfolioValueCzk,
			targetMonthlyExpensesCzk: $targetMonthlyExpensesCzk ?? $trackerInput->targetMonthlyExpensesCzk,
			monthlyContributionCzk: $monthlyContributionCzk ?? $trackerInput->monthlyContributionCzk,
			annualNominalReturnPercentage: $annualNominalReturnPercentage ?? $trackerInput->annualNominalReturnPercentage,
			annualInflationPercentage: $annualInflationPercentage ?? $trackerInput->annualInflationPercentage,
			annualCostPercentage: $annualCostPercentage ?? $trackerInput->annualCostPercentage,
			withdrawalRatePercentage: $withdrawalRatePercentage ?? $trackerInput->withdrawalRatePercentage,
			birthYear: $birthYear ?? $trackerInput->birthYear,
			plannedLifespanAge: $plannedLifespanAge ?? $trackerInput->plannedLifespanAge,
		);
		$this->projectionStartDate = $this->datetimeFactory->createToday();

		try {
			$this->projectionHorizonMonths = $this->resolveProjectionHorizonMonths();
			$this->result = $this->calculator->calculate(
				$this->calculationInput,
				$this->projectionHorizonMonths,
			);
		} catch (InvalidArgumentException) {
			$this->flashMessage(
				'Parametry modelu nejsou platné. Byly obnoveny hodnoty z trackeru.',
				FlashMessageType::DANGER,
			);
			$this->redirect('default');
		}
	}

	public function renderDefault(): void
	{
		if (
			$this->defaults === null
			|| $this->calculationInput === null
			|| $this->result === null
			|| $this->projectionStartDate === null
			|| $this->projectionHorizonMonths === null
		) {
			$this->error('Financial independence model is not initialized.');
		}

		$this->template->heading = 'Finanční nezávislost';
		$this->template->defaults = $this->defaults;
		$this->template->calculationInput = $this->calculationInput;
		$this->template->result = $this->result;
		$this->template->periodEndInclusive = $this->defaults->periodEndExclusive->deductDaysFromDatetime(1);
		$this->template->targetDate = $this->resolveTargetDate($this->result->monthsToTarget);
		$this->template->targetAge = $this->resolveTargetAge($this->template->targetDate);
		$this->template->timeToTarget = $this->formatTimeToTarget($this->result->monthsToTarget);
		$this->template->planningEndYear = $this->resolvePlanningEndYear();
		$this->template->withdrawalScenarios = $this->buildWithdrawalScenarios();
	}

	protected function createComponentFinancialIndependenceForm(): AdminForm
	{
		if ($this->calculationInput === null) {
			$this->error('Financial independence input is not initialized.');
		}

		return $this->formFactory->create(
			$this->calculationInput,
			$this->datetimeFactory->createToday()->getYear(),
			function (FinancialIndependenceCalculationInput $input): void {
				$this->redirect('default', [
					'currentPortfolioValueCzk' => $input->currentPortfolioValueCzk,
					'targetMonthlyExpensesCzk' => $input->targetMonthlyExpensesCzk,
					'monthlyContributionCzk' => $input->monthlyContributionCzk,
					'annualNominalReturnPercentage' => $input->annualNominalReturnPercentage,
					'annualInflationPercentage' => $input->annualInflationPercentage,
					'annualCostPercentage' => $input->annualCostPercentage,
					'withdrawalRatePercentage' => $input->withdrawalRatePercentage,
					'birthYear' => $input->birthYear,
					'plannedLifespanAge' => $input->plannedLifespanAge,
				]);
			},
		);
	}

	protected function createComponentProjectionChart(): ChartControl
	{
		if ($this->result === null || $this->projectionStartDate === null) {
			$this->error('Financial independence projection is not initialized.');
		}

		return $this->chartControlFactory->create(
			ChartType::LINE,
			new FinancialIndependenceProjectionChartDataProvider($this->result, $this->projectionStartDate),
		);
	}

	/**
	 * @return list<array{
	 *     withdrawalRatePercentage: float,
	 *     result: FinancialIndependenceCalculationResult,
	 *     targetDate: ImmutableDateTime|null,
	 *     targetAge: int|null,
	 *     timeToTarget: string
	 * }>
	 */
	private function buildWithdrawalScenarios(): array
	{
		if ($this->calculationInput === null || $this->projectionHorizonMonths === null) {
			return [];
		}

		$scenarios = [];
		foreach (self::WITHDRAWAL_SCENARIO_RATES as $withdrawalRatePercentage) {
			$result = $this->calculator->calculate(
				new FinancialIndependenceCalculationInput(
					currentPortfolioValueCzk: $this->calculationInput->currentPortfolioValueCzk,
					targetMonthlyExpensesCzk: $this->calculationInput->targetMonthlyExpensesCzk,
					monthlyContributionCzk: $this->calculationInput->monthlyContributionCzk,
					annualNominalReturnPercentage: $this->calculationInput->annualNominalReturnPercentage,
					annualInflationPercentage: $this->calculationInput->annualInflationPercentage,
					annualCostPercentage: $this->calculationInput->annualCostPercentage,
					withdrawalRatePercentage: $withdrawalRatePercentage,
					birthYear: $this->calculationInput->birthYear,
					plannedLifespanAge: $this->calculationInput->plannedLifespanAge,
				),
				$this->projectionHorizonMonths,
			);
			$targetDate = $this->resolveTargetDate($result->monthsToTarget);
			$scenarios[] = [
				'withdrawalRatePercentage' => $withdrawalRatePercentage,
				'result' => $result,
				'targetDate' => $targetDate,
				'targetAge' => $this->resolveTargetAge($targetDate),
				'timeToTarget' => $this->formatTimeToTarget($result->monthsToTarget),
			];
		}

		return $scenarios;
	}

	private function resolveTargetDate(int|null $monthsToTarget): ImmutableDateTime|null
	{
		if ($monthsToTarget === null) {
			return null;
		}

		if ($this->projectionStartDate === null) {
			return null;
		}

		return $this->projectionStartDate->addMonthsToDatetime($monthsToTarget);
	}

	private function resolveTargetAge(ImmutableDateTime|null $targetDate): int|null
	{
		if ($targetDate === null || $this->calculationInput === null) {
			return null;
		}

		return $targetDate->getYear() - $this->calculationInput->birthYear;
	}

	private function formatTimeToTarget(int|null $monthsToTarget): string
	{
		if ($this->calculationInput === null) {
			throw new InvalidArgumentException('Financial independence input is not initialized.');
		}

		if ($monthsToTarget === null) {
			return sprintf(
				'Nedosaženo do věku %d let',
				$this->calculationInput->plannedLifespanAge,
			);
		}

		if ($monthsToTarget === 0) {
			return 'Cíl je dosažen';
		}

		$years = intdiv($monthsToTarget, 12);
		$months = $monthsToTarget % 12;
		if ($years === 0) {
			return sprintf('%d měsíců', $months);
		}

		if ($months === 0) {
			return sprintf('%d let', $years);
		}

		return sprintf('%d let a %d měsíců', $years, $months);
	}

	private function resolveProjectionHorizonMonths(): int
	{
		if ($this->calculationInput === null || $this->projectionStartDate === null) {
			throw new InvalidArgumentException('Financial independence projection input is not initialized.');
		}

		$currentYear = $this->projectionStartDate->getYear();
		if ($this->calculationInput->birthYear < 1900 || $this->calculationInput->birthYear > $currentYear) {
			throw new InvalidArgumentException('Birth year must be between 1900 and the current year.');
		}

		if (
			$this->calculationInput->plannedLifespanAge < 1
			|| $this->calculationInput->plannedLifespanAge > 100
		) {
			throw new InvalidArgumentException('Planned lifespan age must be between 1 and 100.');
		}

		$planningEndYear = $this->resolvePlanningEndYear();
		if ($planningEndYear < $currentYear) {
			throw new InvalidArgumentException('Planned lifespan must not end before the current year.');
		}

		return ($planningEndYear - $currentYear) * 12;
	}

	private function resolvePlanningEndYear(): int
	{
		if ($this->calculationInput === null) {
			throw new InvalidArgumentException('Financial independence input is not initialized.');
		}

		return $this->calculationInput->birthYear + $this->calculationInput->plannedLifespanAge;
	}

}
