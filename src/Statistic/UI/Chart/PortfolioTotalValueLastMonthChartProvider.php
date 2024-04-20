<?php

declare(strict_types = 1);

namespace App\Statistic\UI\Chart;

use App\Statistic\PortfolioStatisticRepository;
use App\Statistic\PortolioStatisticType;
use App\UI\Control\Chart\ChartData;
use App\UI\Control\Chart\ChartDataProvider;
use App\UI\Control\Chart\ChartDataSet;
use InvalidArgumentException;
use Mistrfilda\Datetime\DatetimeFactory;

class PortfolioTotalValueLastMonthChartProvider implements ChartDataProvider
{

	/** @var array<PortolioStatisticType>|null */
	private array|null $types = null;

	public function __construct(
		private PortfolioStatisticRepository $portfolioStatisticRepository,
		private DatetimeFactory $datetimeFactory,
	)
	{
	}

	public function addType(PortolioStatisticType $type): void
	{
		$this->types[] = $type;
	}

	public function getChartData(): ChartDataSet
	{
		if ($this->types === null) {
			throw new InvalidArgumentException();
		}

		$allChartData = [];
		foreach ($this->types as $type) {
			$chartData = new ChartData($type->format());

			$addedDates = [];
			foreach ($this->portfolioStatisticRepository->getPortfolioTotalValueForTypeForGreaterDate(
				$type,
				$this->datetimeFactory->createNow()->deductDaysFromDatetime(100),
			) as $portfolioStatistic) {
				$date = $portfolioStatistic->getCreatedAt()->format('Y-m-d');
				if (in_array($date, $addedDates, true)) {
					continue;
				}

				$addedDates[] = $portfolioStatistic->getCreatedAt()->format('Y-m-d');

				if ($type === PortolioStatisticType::TOTAL_PROFIT_PERCENTAGE) {
					$value = str_replace('%', '', $portfolioStatistic->getValue());
					$value = str_replace(' ', '', $value);
				} else {
					$value = str_replace('CZK', '', $portfolioStatistic->getValue());
					$value = str_replace(' ', '', $value);
				}

				$chartData->add($date, (int) $value);
			}

			$allChartData[] = $chartData;
		}

		return new ChartDataSet($allChartData, tooltipSuffix: 'Kč');
	}

}
