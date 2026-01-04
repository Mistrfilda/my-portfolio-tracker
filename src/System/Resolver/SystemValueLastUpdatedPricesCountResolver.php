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

	private const CRON_UPDATE_MINUTE = 25;

	public function __construct(
		private StockAssetRepository $stockAssetRepository,
		private DatetimeFactory $datetimeFactory,
	)
	{

	}

	public function getValueForEnum(SystemValueEnum $systemValueEnum): string|int|ImmutableDateTime|null
	{
		$now = $this->datetimeFactory->createNow();
		$lastUpdateDateTime = $this->getLastScheduledUpdateDateTime($now);

		return $this->stockAssetRepository->getCountUpdatedPricesSince($lastUpdateDateTime);
	}

	private function getLastScheduledUpdateDateTime(ImmutableDateTime $now): ImmutableDateTime
	{
		$today = $this->datetimeFactory->createToday();

		$updateTimes = [
			['hour' => self::CRON_THIRD_UPDATE_HOUR, 'minute' => self::CRON_UPDATE_MINUTE],
			['hour' => self::CRON_SECOND_UPDATE_HOUR, 'minute' => self::CRON_UPDATE_MINUTE],
			['hour' => self::CRON_FIRST_UPDATE_HOUR, 'minute' => self::CRON_UPDATE_MINUTE],
		];

		if ($today->isWeekend()) {
			$lastFriday = $now->modify('last friday');
			return $lastFriday->setTime(self::CRON_THIRD_UPDATE_HOUR, self::CRON_UPDATE_MINUTE);
		}

		foreach ($updateTimes as $updateTime) {
			if (
				$now->getHour() > $updateTime['hour']
				|| ($now->getHour() === $updateTime['hour'] && $now->getMinutes() >= $updateTime['minute'])
			) {
				return $today->setTime($updateTime['hour'], $updateTime['minute']);
			}
		}

		$yesterday = $today->deductDaysFromDatetime(1);

		if ($yesterday->isWeekend()) {
			$lastFriday = $yesterday->modify('last friday');
			return $lastFriday->setTime(self::CRON_THIRD_UPDATE_HOUR, self::CRON_UPDATE_MINUTE);
		}

		return $yesterday->setTime(self::CRON_THIRD_UPDATE_HOUR, self::CRON_UPDATE_MINUTE);
	}

}
