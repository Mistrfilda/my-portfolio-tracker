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

	/**
	 * @param array<mixed> $parameters
	 */
	public function processParametersFromRequest(array $parameters): void
	{
		//do nothing
	}

	public function getChartData(): ChartDataSet
	{
		if ($this->types === null) {
			throw new InvalidArgumentException();
		}

		$allChartData = [];
		foreach ($this->types as $type) {
			$chartData = new ChartData($type->format());

			/** @var array<string, int> $values */
			$values = [];
			foreach ($this->portfolioStatisticRepository->getPortfolioTotalValueForTypeForGreaterDate(
				$type,
				$this->datetimeFactory->createNow()->deductDaysFromDatetime(100),
			) as $portfolioStatistic) {
				$date = $portfolioStatistic->getCreatedAt()->format('Y-m-d');

				if ($type === PortolioStatisticType::TOTAL_PROFIT_PERCENTAGE) {
					$value = str_replace('%', '', $portfolioStatistic->getValue());
					$value = str_replace(' ', '', $value);
				} else {
					$value = str_replace('CZK', '', $portfolioStatistic->getValue());
					$value = str_replace(' ', '', $value);
				}

				$values[$date] = (int) $value;
			}

			foreach ($values as $key => $value) {
				$chartData->add($key, $value);
			}

			$allChartData[] = $chartData;
		}

		return new ChartDataSet($allChartData, tooltipSuffix: 'Kč');
	}

	public function getIdForChart(): string
	{
		$id = md5(self::class);
		foreach ($this->types ?? [] as $type) {
			$id .= md5($type::class);
		}

		return $id;
	}

}
