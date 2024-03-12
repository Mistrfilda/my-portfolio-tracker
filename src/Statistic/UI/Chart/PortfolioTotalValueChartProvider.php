<?php

declare(strict_types = 1);

namespace App\Statistic\UI\Chart;

use App\Statistic\PortfolioStatisticRepository;
use App\Statistic\PortolioStatisticType;
use App\UI\Control\Chart\ChartData;
use App\UI\Control\Chart\ChartDataProvider;
use InvalidArgumentException;

class PortfolioTotalValueChartProvider implements ChartDataProvider
{

	private PortolioStatisticType|null $type = null;

	public function __construct(
		private PortfolioStatisticRepository $portfolioStatisticRepository,
	)
	{
	}

	public function setType(PortolioStatisticType $type): void
	{
		$this->type = $type;
	}

	public function getChartData(): ChartData
	{
		if ($this->type === null) {
			throw new InvalidArgumentException();
		}

		$chartData = new ChartData($this->type->format(), tooltipSuffix: 'Kč');

		$addedDates = [];
		foreach ($this->portfolioStatisticRepository->getPortfolioTotalValueForType(
			$this->type,
		) as $portfolioStatistic) {
			$date = $portfolioStatistic->getCreatedAt()->format('Y-m-d');
			if (in_array($date, $addedDates, true)) {
				continue;
			}

			$addedDates[] = $portfolioStatistic->getCreatedAt()->format('Y-m-d');

			if ($this->type === PortolioStatisticType::TOTAL_PROFIT_PERCENTAGE) {
				$value = str_replace('%', '', $portfolioStatistic->getValue());
				$value = str_replace(' ', '', $value);
			} else {
				$value = str_replace('CZK', '', $portfolioStatistic->getValue());
				$value = str_replace(' ', '', $value);
			}

			$chartData->add($date, (int) $value);
		}

		return $chartData;
	}

}
