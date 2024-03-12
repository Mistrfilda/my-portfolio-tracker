<?php

declare(strict_types = 1);

namespace App\Stock\Price\Downloader\TwelveData;

use App\Stock\Asset\StockAsset;

class TwelveDataRequest
{

	private const URL = 'https://api.twelvedata.com/price?symbol=%s&apikey=%s';

	private string $apiKey;

	/** @var array<string, StockAsset> */
	private array $tickers;

	public function __construct(string $apiKey)
	{
		$this->apiKey = $apiKey;
		$this->tickers = [];
	}

	public function addStockAsset(StockAsset $stockAsset): void
	{
		$this->tickers[$stockAsset->getTicker()] = $stockAsset;
	}

	public function count(): int
	{
		return count($this->tickers);
	}

	public function getFormattedTickers(): string
	{
		return implode(',', array_keys($this->tickers));
	}

	public function getStockAssetForTicker(string $ticker): StockAsset|null
	{
		if ($this->count() === 1) {
			$stockAsset = reset($this->tickers);
			return $stockAsset === false ? null : $stockAsset;
		}

		if (array_key_exists($ticker, $this->tickers)) {
			return $this->tickers[$ticker];
		}

		return null;
	}

	public function getFormattedRequestUrl(): string
	{
		return sprintf(
			self::URL,
			$this->getFormattedTickers(),
			$this->apiKey,
		);
	}

}
