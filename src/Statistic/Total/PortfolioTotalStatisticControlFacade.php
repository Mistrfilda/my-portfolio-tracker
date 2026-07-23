<?php

declare(strict_types = 1);

namespace App\Statistic\Total;

use App\Statistic\Performance\PortfolioPerformanceProvider;
use App\Statistic\PortfolioStatisticRecordRepository;
use App\Statistic\PortolioStatisticType;
use InvalidArgumentException;
use Mistrfilda\Datetime\DatetimeFactory;

class PortfolioTotalStatisticControlFacade
{

	public function __construct(
		private int $startYear,
		private DatetimeFactory $datetimeFactory,
		private PortfolioStatisticRecordRepository $portfolioStatisticRecordRepository,
		private PortfolioPerformanceProvider $portfolioPerformanceProvider,
	)
	{

	}

	/**
	 * @return array<PortfolioStatisticTotalGroup>
	 */
	public function getData(): array
	{
		$now = $this->datetimeFactory->createNow();
		$currentYear = $now->getYear();

		$groups = [];

		$firstYearValue = null;
		$lastYearValue = null;
		for ($year = $this->startYear; $year <= $currentYear; $year++) {
			$group = new PortfolioStatisticTotalGroup($year);

			$minMaxValuesByMonth = $this->portfolioStatisticRecordRepository->findMinMaxDateByMonth($year);

			$month = null;
			$firstMonthValue = null;

			foreach ($minMaxValuesByMonth as $portfolioStatisticRecord) {
				if ($firstYearValue === null) {
					$firstYearValue = $portfolioStatisticRecord;
				}

				if ($month === null || $month !== $portfolioStatisticRecord->getCreatedAt()->getMonth()) {
					$month = $portfolioStatisticRecord->getCreatedAt()->getMonth();
					$firstMonthValue = $portfolioStatisticRecord;
					continue;
				}

				if ($firstMonthValue === null) {
					throw new InvalidArgumentException();
				}

				if ($firstMonthValue->getCreatedAt()->getMonth() !== $portfolioStatisticRecord->getCreatedAt()->getMonth()) {
					throw new InvalidArgumentException();
				}

				$income = $this->portfolioPerformanceProvider->getIncomeBetween(
					$firstMonthValue->getCreatedAt(),
					$portfolioStatisticRecord->getCreatedAt(),
				);
				$group->addValue(
					new PortfolioStatisticTotalValue(
						$month,
						sprintf(
							'%s - %s',
							$firstMonthValue->getCreatedAt()->format(DatetimeFactory::DEFAULT_DATE_FORMAT),
							$portfolioStatisticRecord->getCreatedAt()->format(DatetimeFactory::DEFAULT_DATE_FORMAT),
						),
						$this->parseStatisticIntoFloat(
							$firstMonthValue->getPortfolioStatisticByType(
								PortolioStatisticType::TOTAL_INVESTED_IN_CZK,
							)?->getValue(),
						),
						$this->parseStatisticIntoFloat(
							$portfolioStatisticRecord->getPortfolioStatisticByType(
								PortolioStatisticType::TOTAL_INVESTED_IN_CZK,
							)?->getValue(),
						),
						$this->parseStatisticIntoFloat(
							$firstMonthValue->getPortfolioStatisticByType(
								PortolioStatisticType::TOTAL_VALUE_IN_CZK,
							)?->getValue(),
						),
						$this->parseStatisticIntoFloat(
							$portfolioStatisticRecord->getPortfolioStatisticByType(
								PortolioStatisticType::TOTAL_VALUE_IN_CZK,
							)?->getValue(),
						),
						$income->realizedProfit,
						$income->netDividends,
						$firstMonthValue->getCreatedAt(),
						$portfolioStatisticRecord->getCreatedAt(),
						performanceSummary: $this->portfolioPerformanceProvider->getSummaryBetween(
							$firstMonthValue->getCreatedAt(),
							$portfolioStatisticRecord->getCreatedAt(),
						),
					),
				);

				$lastYearValue = $portfolioStatisticRecord;
			}

			if ($lastYearValue === null && $firstYearValue !== null) {
				$lastYearValue = $firstYearValue;
			}

			if ($lastYearValue === null || $firstYearValue === null) {
				throw new InvalidArgumentException();
			}

			$income = $this->portfolioPerformanceProvider->getIncomeBetween(
				$firstYearValue->getCreatedAt(),
				$lastYearValue->getCreatedAt(),
			);
			$group->setYearValue(
				new PortfolioStatisticTotalValue(
					null,
					sprintf(
						'Rok %s',
						$firstYearValue->getCreatedAt()->format('Y'),
					),
					$this->parseStatisticIntoFloat(
						$firstYearValue->getPortfolioStatisticByType(
							PortolioStatisticType::TOTAL_INVESTED_IN_CZK,
						)?->getValue(),
					),
					$this->parseStatisticIntoFloat(
						$lastYearValue->getPortfolioStatisticByType(
							PortolioStatisticType::TOTAL_INVESTED_IN_CZK,
						)?->getValue(),
					),
					$this->parseStatisticIntoFloat(
						$firstYearValue->getPortfolioStatisticByType(
							PortolioStatisticType::TOTAL_VALUE_IN_CZK,
						)?->getValue(),
					),
					$this->parseStatisticIntoFloat(
						$lastYearValue->getPortfolioStatisticByType(
							PortolioStatisticType::TOTAL_VALUE_IN_CZK,
						)?->getValue(),
					),
					$income->realizedProfit,
					$income->netDividends,
					$firstYearValue->getCreatedAt(),
					$lastYearValue->getCreatedAt(),
					performanceSummary: $this->portfolioPerformanceProvider->getSummaryBetween(
						$firstYearValue->getCreatedAt(),
						$lastYearValue->getCreatedAt(),
					),
				),
			);

			$groups[] = $group;
			$firstYearValue = null;
			$lastYearValue = null;
		}

		return $groups;
	}

	private function parseStatisticIntoFloat(string|null $value, bool $isPercentage = false): int
	{
		if ($value === null) {
			throw new InvalidArgumentException();
		}

		if ($isPercentage) {
			$value = str_replace('%', '', $value);
			$value = str_replace(' ', '', $value);
		} else {
			$value = str_replace('CZK', '', $value);
			$value = str_replace(' ', '', $value);
		}

		return (int) $value;
	}

}
