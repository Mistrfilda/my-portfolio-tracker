<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Download;

use App\Stock\Price\StockAssetPriceDownloaderEnum;
use LogicException;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class StockAssetDataFreshness
{

	/** @param array<string, int> $priceThresholdHours */
	public function __construct(
		private DatetimeFactory $datetimeFactory,
		private array $priceThresholdHours,
		private int $completionGraceMinutes = 45,
		private int $initialDownloadGraceMinutes = 15,
	)
	{
	}

	public function getInitialDownloadDeadline(): ImmutableDateTime
	{
		return $this->datetimeFactory->createNow()->modify(sprintf('-%d minutes', $this->initialDownloadGraceMinutes));
	}

	public function getCutoff(
		StockAssetDataType $type,
		StockAssetPriceDownloaderEnum|null $source = null,
	): ImmutableDateTime
	{
		$now = $this->datetimeFactory->createNow()->modify(sprintf('-%d minutes', $this->completionGraceMinutes));
		for ($days = 0; $days < 8; $days++) {
			$date = $now->deductDaysFromDatetime($days);
			$weekday = (int) $date->format('N');
			$hours = match ($type) {
				StockAssetDataType::PRICE => $weekday <= 5 ? [22, 16, 12] : [],
				StockAssetDataType::DIVIDENDS => in_array($weekday, [1, 3, 5], true) ? [6] : [],
				StockAssetDataType::VALUATION, StockAssetDataType::ANALYST_INSIGHTS => $weekday === 6 ? [6] : [],
			};
			foreach ($hours as $hour) {
				$cycle = $date->setTime($hour, $type === StockAssetDataType::PRICE ? 25 : 0);
				if ($cycle <= $now) {
					return $cycle->deductHoursFromDatetime(
						$source === null ? 0 : $this->priceThresholdHours[$source->value],
					);
				}
			}
		}

		throw new LogicException('No scheduled stock update found.');
	}

}
