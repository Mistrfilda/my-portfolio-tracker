<?php

declare(strict_types = 1);

namespace App\Stock\Price\Downloader\Json;

use App\Stock\Asset\StockAsset;
use Mistrfilda\Datetime\DatetimeFactory;

class JsonWebDataService
{

	public function __construct(
		private string $stockAssetPriceUrl,
		private string $stockAssetDividendPriceUrl,
		private string $financialsDataUrl,
		private string $keyStatisticsDataUrl,
		private DatetimeFactory $datetimeFactory,
	)
	{

	}

	public function getStockAssetPriceUrl(StockAsset $stockAsset): string
	{
		return sprintf(
			$this->stockAssetPriceUrl,
			$stockAsset->getTicker(),
		);
	}

	public function getStockAssetDividendsUrl(StockAsset $stockAsset): string
	{
		return sprintf(
			$this->stockAssetDividendPriceUrl,
			$stockAsset->getTicker(),
			$this->datetimeFactory->createToday()->deductYearsFromDatetime(5)->getTimestamp(),
			$this->datetimeFactory->createToday()->deductDaysFromDatetime(1)->getTimestamp(),
		);
	}

	public function getFinancialsDataUrl(StockAsset $stockAsset): string
	{
		return sprintf(
			$this->financialsDataUrl,
			$stockAsset->getTicker(),
		);
	}

	public function getKeyStatisticsDataUrl(StockAsset $stockAsset): string
	{
		return sprintf(
			$this->keyStatisticsDataUrl,
			$stockAsset->getTicker(),
		);
	}

}
