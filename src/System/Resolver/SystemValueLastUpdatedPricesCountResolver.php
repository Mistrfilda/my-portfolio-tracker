<?php

declare(strict_types = 1);

namespace App\System\Resolver;

use App\Stock\Asset\StockAssetRepository;
use App\System\SystemValueEnum;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class SystemValueLastUpdatedPricesCountResolver implements SystemValueResolver
{

	private const CRON_FIRST_UPDATE_HOUR = 12;

	private const CRON_SECOND_UPDATE_HOUR = 16;

	private const CRON_THIRD_UPDATE_HOUR = 22;

	private const CRON_UPDATE_MINUTE = 35;

	public function __construct(
		private StockAssetRepository $stockAssetRepository,
		private DatetimeFactory $datetimeFactory,
	)
	{

	}

	public function getValueForEnum(SystemValueEnum $systemValueEnum): string|int|ImmutableDateTime|null
	{
		$now = $this->datetimeFactory->createNow();
		$lastUpdateDate = $this->datetimeFactory->createToday();
		$lastUpdatedHour = self::CRON_SECOND_UPDATE_HOUR;

		if (
			$now->getHour() < self::CRON_FIRST_UPDATE_HOUR
			|| (
				$now->getHour() === self::CRON_FIRST_UPDATE_HOUR
				&& $now->getMinutes() < self::CRON_UPDATE_MINUTE
			)
		) {
			$lastUpdateDate = $lastUpdateDate->deductDaysFromDatetime(1);
			$lastUpdatedHour = self::CRON_THIRD_UPDATE_HOUR;
		} elseif (
			$now->getHour() >= self::CRON_THIRD_UPDATE_HOUR
			&& (
				$now->getMinutes() > self::CRON_UPDATE_MINUTE
				|| $now->getHour() > self::CRON_THIRD_UPDATE_HOUR
			)
		) {
			$lastUpdatedHour = self::CRON_THIRD_UPDATE_HOUR;
		} elseif (
			$now->getHour() >= self::CRON_SECOND_UPDATE_HOUR
			&& (
				$now->getMinutes() > self::CRON_UPDATE_MINUTE
				|| $now->getHour() > self::CRON_SECOND_UPDATE_HOUR
			)
		) {
			$lastUpdatedHour = self::CRON_SECOND_UPDATE_HOUR;
		} elseif (
			$now->getMinutes() > self::CRON_UPDATE_MINUTE
			|| $now->getHour() > self::CRON_FIRST_UPDATE_HOUR
		) {
			$lastUpdatedHour = self::CRON_FIRST_UPDATE_HOUR;
		}

		if ($lastUpdateDate->isWeekend()) {
			$lastUpdateDate = $now->modify('last friday');
			$lastUpdatedHour = self::CRON_THIRD_UPDATE_HOUR;
		}

		return $this->stockAssetRepository->getCountUpdatedPricesAt($lastUpdateDate, $lastUpdatedHour);
	}

}
