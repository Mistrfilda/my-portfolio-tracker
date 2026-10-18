<?php

declare(strict_types = 1);

namespace App\Dashboard;

use App\Admin\AppAdmin;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use Mistrfilda\Datetime\DatetimeFactory;

class DashboardStockPerformanceProvider
{

	public function __construct(
		private StockAssetRepository $stocks,
		private DatetimeFactory $datetimeFactory,
	)
	{
	}

	/** @return list<array{stockAsset: StockAsset, trend: float}> */
	public function getDailyPerformance(AppAdmin $appAdmin): array
	{
		$comparisonDate = $this->datetimeFactory->createNow()->deductDaysFromDatetime(1);
		$performance = [];
		foreach ($this->stocks->findWithPositionsForAppAdmin($appAdmin, true) as $asset) {
			if (!$asset->shouldBeUpdated()) {
				continue;
			}

			$performance[] = ['stockAsset' => $asset, 'trend' => $asset->getTrend($comparisonDate)];
		}

		usort(
			$performance,
			static function (array $left, array $right): int {
				$comparison = $right['trend'] <=> $left['trend'];
				return $comparison === 0
					? strcmp($left['stockAsset']->getId()->toString(), $right['stockAsset']->getId()->toString())
					: $comparison;
			},
		);
		return $performance;
	}

}
