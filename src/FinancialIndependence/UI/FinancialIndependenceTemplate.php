<?php

declare(strict_types = 1);

namespace App\FinancialIndependence\UI;

use App\FinancialIndependence\FinancialIndependenceCalculationInput;
use App\FinancialIndependence\FinancialIndependenceCalculationResult;
use App\FinancialIndependence\FinancialIndependenceDefaults;
use App\UI\Base\BaseAdminPresenterTemplate;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class FinancialIndependenceTemplate extends BaseAdminPresenterTemplate
{

	public FinancialIndependenceDefaults $defaults;

	public FinancialIndependenceCalculationInput $calculationInput;

	public FinancialIndependenceCalculationResult $result;

	public ImmutableDateTime $periodEndInclusive;

	public ImmutableDateTime|null $targetDate;

	public int|null $targetAge;

	public string $timeToTarget;

	public int $planningEndYear;

	/**
	 * @var list<array{
	 *     withdrawalRatePercentage: float,
	 *     result: FinancialIndependenceCalculationResult,
	 *     targetDate: ImmutableDateTime|null,
	 *     targetAge: int|null,
	 *     timeToTarget: string
	 * }>
	 */
	public array $withdrawalScenarios;

}
