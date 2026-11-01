<?php

declare(strict_types = 1);

namespace App\Dashboard;

use App\Admin\AppAdmin;
use App\Statistic\PortfolioStatisticCache;
use App\Stock\Asset\StockAssetRepository;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class DashboardStockPerformanceProvider
{

	public function __construct(
		private StockAssetRepository $stocks,
		private DatetimeFactory $datetimeFactory,
		private readonly PortfolioStatisticCache $cache,
	)
	{
	}

	/** @return list<array{id: string, name: string, ticker: string, trend: float}> */
	public function getDailyPerformance(AppAdmin $appAdmin): array
	{
		$comparisonDate = $this->datetimeFactory->createNow()->deductDaysFromDatetime(1);
		return $this->cache->load(
			$appAdmin,
			'stock-daily-performance-' . $comparisonDate->format('Y-m-d'),
			fn (): array => $this->calculateDailyPerformance($appAdmin, $comparisonDate),
		);
	}

	/** @return list<array{id: string, name: string, ticker: string, trend: float}> */
	private function calculateDailyPerformance(AppAdmin $appAdmin, ImmutableDateTime $comparisonDate): array
	{
		$performance = [];
		foreach ($this->stocks->findWithPositionsForAppAdmin($appAdmin, true) as $asset) {
			if (!$asset->shouldBeUpdated()) {
				continue;
			}

			$performance[] = [
				'id' => $asset->getId()->toString(),
				'name' => $asset->getName(),
				'ticker' => $asset->getTicker(),
				'trend' => $asset->getTrend($comparisonDate),
			];
		}

		usort(
			$performance,
			static function (array $left, array $right): int {
				$comparison = $right['trend'] <=> $left['trend'];
				return $comparison === 0
					? strcmp($left['id'], $right['id'])
					: $comparison;
			},
		);
		return $performance;
	}

}
