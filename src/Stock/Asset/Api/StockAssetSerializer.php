<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Api;

use App\Stock\Asset\StockAsset;
use const DATE_ATOM;

class StockAssetSerializer
{

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
		return [
			'id' => $stockAsset->getId()->toString(),
			'name' => $stockAsset->getName(),
			'ticker' => $stockAsset->getTicker(),
			'isin' => $stockAsset->getIsin(),
			'exchange' => $stockAsset->getExchange()->value,
			'currency' => $stockAsset->getCurrency()->value,
			'price' => $stockAsset->getAssetCurrentPrice()->getPrice(),
			'priceDownloadedAt' => $stockAsset->getPriceDownloadedAt()->format(DATE_ATOM),
			'assetPriceDownloader' => $stockAsset->getAssetPriceDownloader()->value,
		];
	}

}
