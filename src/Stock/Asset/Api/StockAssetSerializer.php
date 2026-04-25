<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Api;

use App\Stock\Asset\StockAsset;
use Mistrfilda\Datetime\DatetimeFactory;
use const DATE_ATOM;

class StockAssetSerializer
{

	public function __construct(
		private readonly DatetimeFactory $datetimeFactory,
	)
	{
	}

	/**
	 * @param array<StockAsset> $stockAssets
	 * @return array<mixed>
	 */
	public function serializeList(array $stockAssets): array
	{
		$data = [];
		foreach ($stockAssets as $stockAsset) {
			$data[] = $this->serialize($stockAsset);
		}

		return $data;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function serialize(StockAsset $stockAsset): array
	{
		$now = $this->datetimeFactory->createNow();
		$oneDayChange = $stockAsset->getTrend($now->deductDaysFromDatetime(1));
		$sevenDayChange = $stockAsset->getTrend($now->deductDaysFromDatetime(7));
		$thirtyDayChange = $stockAsset->getTrend($now->deductDaysFromDatetime(30));

		return [
			'id' => $stockAsset->getId()->toString(),
			'name' => $stockAsset->getName(),
			'ticker' => $stockAsset->getTicker(),
			'isin' => $stockAsset->getIsin(),
			'exchange' => $stockAsset->getExchange()->value,
			'currency' => $stockAsset->getCurrency()->value,
			'price' => $stockAsset->getAssetCurrentPrice()->getPrice(),
			'trend' => $oneDayChange >= 0 ? 'increasing' : 'decreasing',
			'oneDayChange' => $oneDayChange,
			'sevenDayChange' => $sevenDayChange,
			'thirtyDayChange' => $thirtyDayChange,
			'priceDownloadedAt' => $stockAsset->getPriceDownloadedAt()->format(DATE_ATOM),
			'assetPriceDownloader' => $stockAsset->getAssetPriceDownloader()->value,
		];
	}

}
