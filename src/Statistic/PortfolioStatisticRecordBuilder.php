<?php

declare(strict_types = 1);

namespace App\Statistic;

use App\Dashboard\DashboardValue;
use App\Dashboard\DashboardValueBuilder;
use App\Dashboard\DashboardValueGroup;

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
		foreach ($record->getPortfolioStatistics() as $statistic) {
			$valuesGroups[$statistic->getDashboardValueGroup()->name] = $statistic->getDashboardValueGroup();
			$values[$statistic->getDashboardValueGroup()->name][] = new DashboardValue(
				$statistic->getLabel(),
				$statistic->getValue(),
				$statistic->getColor(),
				$statistic->getSvgIcon(),
				$statistic->getDescription(),
			);
		}

		$sortedGroups = [];
		foreach ($valuesGroups as $valueGroup) {
			$sortedGroups[] = new DashboardValueGroup(
				$valueGroup,
				$valueGroup->heading(),
				$valueGroup->description(),
				$values[$valueGroup->name],
			);
		}

		return $sortedGroups;
	}

}
