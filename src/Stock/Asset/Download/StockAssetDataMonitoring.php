<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Download;

use App\Stock\Asset\StockAssetRepository;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\System\SystemValueEnum;
use App\System\SystemValueFacade;
use App\System\SystemValueRepository;
use Mistrfilda\Datetime\DatetimeFactory;
use RuntimeException;

class StockAssetDataMonitoring
{

	public function __construct(
		private StockAssetRepository $assets,
		private StockAssetDataFreshness $freshness,
		private SystemValueRepository $systemValues,
		private SystemValueFacade $systemValueFacade,
		private DatetimeFactory $datetimeFactory,
	)
	{
	}

	public function isEnabled(): bool
	{
		return $this->systemValues->findByEnum(
			SystemValueEnum::STOCK_DATA_MONITORING_ENABLED_AT,
		)?->getDatetimeValue() !== null;
	}

	public function getFreshCount(StockAssetDataType $type): int
	{
		$count = 0;
		foreach ($type === StockAssetDataType::PRICE ? StockAssetPriceDownloaderEnum::cases() : [null] as $source) {
			$count += $this->assets->countForDataType($type, $this->freshness->getCutoff($type, $source), $source);
		}

		return $count;
	}

	public function isHealthy(StockAssetDataType $type): bool
	{
		foreach ($type === StockAssetDataType::PRICE ? StockAssetPriceDownloaderEnum::cases() : [null] as $source) {
			if ($this->assets->countStaleForDataType(
				$type,
				$this->freshness->getCutoff($type, $source),
				$this->freshness->getInitialDownloadDeadline(),
				$source,
			) !== 0) {
				return false;
			}
		}

		return true;
	}

	public function enable(): void
	{
		foreach (StockAssetDataType::cases() as $type) {
			if ($this->assets->countForDataType($type) !== $this->getFreshCount($type)) {
				throw new RuntimeException(
					sprintf(
						'Cannot enable stock monitoring: %s has missing or stale checks. Run the batch downloads first.',
						$type->value,
					),
				);
			}
		}

		$this->systemValueFacade->updateValue(
			SystemValueEnum::STOCK_DATA_MONITORING_ENABLED_AT,
			datetimeValue: $this->datetimeFactory->createNow(),
		);
	}

}
