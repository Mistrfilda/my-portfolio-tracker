<?php

declare(strict_types = 1);

namespace App\FinancialIndependence\UI;

use App\FinancialIndependence\FinancialIndependenceCalculationResult;
use App\UI\Control\Chart\ChartData;
use App\UI\Control\Chart\ChartDataProvider;
use App\UI\Control\Chart\ChartDataSet;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

final readonly class FinancialIndependenceProjectionChartDataProvider implements ChartDataProvider
{

	public function __construct(
		private FinancialIndependenceCalculationResult $result,
		private ImmutableDateTime $projectionStartDate,
	)
	{
	}

	public function getChartData(): ChartDataSet
	{
		$portfolioValue = new ChartData('Odhad hodnoty portfolia', false, tension: 0.25);
		$targetValue = new ChartData('Cílová hodnota', false, true);
		$lastMonth = null;
		foreach ($this->result->projectionPoints as $point) {
			$lastMonth = $point->month;
		}

		foreach ($this->result->projectionPoints as $point) {
			if ($point->month % 12 !== 0 && $point->month !== $lastMonth) {
				continue;
			}

			$label = $this->projectionStartDate->addMonthsToDatetime($point->month)->format('m/Y');
			$portfolioValue->add($label, $point->portfolioValueCzk);
			$targetValue->add($label, $this->result->targetPortfolioValueCzk);
		}

		return new ChartDataSet([$portfolioValue, $targetValue], ' CZK');
	}

	/** @param array<string, string> $parameters */
	public function processParametersFromRequest(array $parameters): void
	{
		// The projection is fully defined by the current calculator input.
	}

	public function getIdForChart(): string
	{
		return 'financial-independence-projection';
	}

}
