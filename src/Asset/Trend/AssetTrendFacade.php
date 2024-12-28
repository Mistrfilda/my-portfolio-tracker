<?php

declare(strict_types = 1);

namespace App\Asset\Trend;

use App\Asset\AssetRepository;
use App\Notification\NotificationChannelEnum;
use App\Notification\NotificationFacade;
use App\Notification\NotificationTypeEnum;
use App\UI\Filter\AssetPriceFilter;
use App\UI\Filter\PercentageFilter;
use Mistrfilda\Datetime\DatetimeFactory;

class AssetTrendFacade
{

	/**
	 * @param array<AssetRepository> $assetRepositories
	 */
	public function __construct(
		private array $assetRepositories,
		private DatetimeFactory $datetimeFactory,
		private NotificationFacade $notificationFacade,
	)
	{
	}

	public function processTrends(
		int $numberOfDaysToCompare,
		int $differenceThreshold = 2,
	): void
	{
		$now = $this->datetimeFactory->createNow();
		foreach ($this->assetRepositories as $assetRepository) {
			foreach ($assetRepository->getAllActiveAssets() as $activeAsset) {
				$trend = $activeAsset->getTrend($now->deductDaysFromDatetime($numberOfDaysToCompare));

				if (abs($trend) > $differenceThreshold) {
					if ($trend > 0) {
						$notificationType = NotificationTypeEnum::PRICE_ALERT_UP;
					} else {
						$notificationType = NotificationTypeEnum::PRICE_ALERT_DOWN;
					}
				} else {
					continue;
				}

				$arrow = $notificationType === NotificationTypeEnum::PRICE_ALERT_DOWN ? 'arrow_down' : 'arrow_up';
				$this->notificationFacade->create(
					$notificationType,
					[NotificationChannelEnum::DISCORD],
					sprintf(
						':%s: Price alert: %s: Aktuální hodnota %s | Změna o %s (časové okno v dnech: %s) :%s:',
						$arrow,
						$activeAsset->getName(),
						AssetPriceFilter::format($activeAsset->getAssetCurrentPrice()),
						PercentageFilter::format($trend),
						$numberOfDaysToCompare,
						$arrow,
					),
				);
			}
		}
	}

}
