<?php

declare(strict_types = 1);

namespace App\Statistic\PeriodStatistic\UI;

use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticChartSectionDTO;
use App\UI\Control\Chart\ChartData;
use App\UI\Control\Chart\ChartDataProvider;
use App\UI\Control\Chart\ChartDataSet;

class PortfolioPeriodStatisticChartDataProvider implements ChartDataProvider
{

	public function __construct(
		private string $reportId,
		private PortfolioPeriodStatisticChartSectionDTO $chartSection,
		private PortfolioPeriodStatisticChartTypeEnum $type,
	)
	{
	}

	public function getChartData(): ChartDataSet
	{
		if ($this->type === PortfolioPeriodStatisticChartTypeEnum::DIVIDENDS_BY_COMPANY) {
			$dividends = new ChartData('Čisté dividendy');
			foreach ($this->chartSection->dividendsByCompany as $point) {
				$dividends->add($point->label, $point->value);
			}

			return new ChartDataSet([$dividends], ' CZK');
		}

		$portfolioValue = new ChartData('Hodnota portfolia', false);
		foreach ($this->chartSection->portfolioValues as $point) {
			$portfolioValue->add($point->label, $point->value);
		}

		$investedValue = new ChartData('Investováno', false);
		foreach ($this->chartSection->investedValues as $point) {
			$investedValue->add($point->label, $point->value);
		}

		return new ChartDataSet([$portfolioValue, $investedValue], ' CZK');
	}

	/** @param array<string, string> $parameters */
	public function processParametersFromRequest(array $parameters): void
	{
		// The report chart is immutable and does not accept request parameters.
	}

	public function getIdForChart(): string
	{
		return sprintf('portfolio-period-%s-%s', $this->reportId, $this->type->value);
	}

}
