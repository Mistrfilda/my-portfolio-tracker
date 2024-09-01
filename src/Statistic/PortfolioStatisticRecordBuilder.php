<?php

declare(strict_types = 1);

namespace App\Statistic;

use App\Dashboard\DashboardValue;
use App\Dashboard\DashboardValueBuilder;
use App\Dashboard\DashboardValueGroup;
use App\Dashboard\DashboardValueTable;

class PortfolioStatisticRecordBuilder implements DashboardValueBuilder
{

	public function __construct(
		private readonly int $portfolioStatisticRecordId,
		private readonly PortfolioStatisticRecordRepository $portfolioStatisticRecordRepository,
	)
	{
	}

	/**
	 * @return array<int, DashboardValueGroup>
	 */
	public function buildValues(): array
	{
		$record = $this->portfolioStatisticRecordRepository->getById(
			$this->portfolioStatisticRecordId,
		);

		$valuesGroups = [];
		$values = [];
		$tables = [];
		foreach ($record->getPortfolioStatistics() as $statistic) {
			$valuesGroups[$statistic->getDashboardValueGroup()->name] = $statistic->getDashboardValueGroup();

			if ($statistic->getPortfolioStatisticControlTypeEnum() === PortfolioStatisticControlTypeEnum::SIMPLE_VALUE) {
				$values[$statistic->getDashboardValueGroup()->name][] = new DashboardValue(
					$statistic->getLabel(),
					$statistic->getValue(),
					$statistic->getColor(),
					$statistic->getSvgIcon(),
					$statistic->getDescription(),
				);
			} else {
				$heading = $statistic->getStructuredData()['heading'] ?? [];
				assert(is_array($heading));

				$data = $statistic->getStructuredData()['data'] ?? [];
				assert(is_array($data));

				$tables[$statistic->getDashboardValueGroup()->name][] = new DashboardValueTable(
					$statistic->getLabel(),
					$statistic->getValue(),
					$statistic->getColor(),
					$heading,
					$data,
				);
			}
		}

		$sortedGroups = [];
		foreach ($valuesGroups as $valueGroup) {
			$groupTables = [];
			if (array_key_exists($valueGroup->name, $tables)) {
				$groupTables = $tables[$valueGroup->name];
			}

			$groupPositions = [];
			if (array_key_exists($valueGroup->name, $values)) {
				$groupPositions = $values[$valueGroup->name];
			}

			$sortedGroups[] = new DashboardValueGroup(
				$valueGroup,
				$valueGroup->heading(),
				$valueGroup->description(),
				$groupPositions,
				tables: $groupTables,
			);
		}

		return $sortedGroups;
	}

}
