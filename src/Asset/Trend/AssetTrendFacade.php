<?php

declare(strict_types = 1);

namespace App\Asset\Trend;

use App\Asset\AssetRepository;
use App\Notification\NotificationChannelEnum;
use App\Notification\NotificationFacade;
use App\Notification\NotificationParameterEnum;
use App\Notification\NotificationParameters;
use App\Notification\NotificationTypeEnum;
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
		$dateToCompare = $now->deductDaysFromDatetime($numberOfDaysToCompare);
		$trends = [];

		foreach ($this->assetRepositories as $assetRepository) {
			foreach ($assetRepository->getAllActiveAssets() as $activeAsset) {
				$trend = $activeAsset->getTrend($dateToCompare);

				if (abs($trend) <= $differenceThreshold) {
					continue;
				}

				$currentPrice = $activeAsset->getAssetCurrentPrice();
				$trends[] = [
					'name' => $activeAsset->getName(),
					'currentPrice' => $currentPrice->getPrice(),
					'currency' => $currentPrice->getCurrency()->value,
					'trend' => $trend,
				];
			}
		}

		if ($trends === []) {
			return;
		}

		$parameters = new NotificationParameters();
		$parameters->addParameter(NotificationParameterEnum::TREND_DAYS_THRESHOLD, $numberOfDaysToCompare);

		$this->notificationFacade->create(
			NotificationTypeEnum::ASSET_TRENDS,
			[NotificationChannelEnum::DISCORD],
			'Asset trends',
			$parameters,
			['trends' => $trends],
		);
	}

}
