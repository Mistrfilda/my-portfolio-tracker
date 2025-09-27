<?php

declare(strict_types = 1);


namespace App\Stock\Valuation\Model\Price;


use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Model\StockValuationModel;
use App\Stock\Valuation\Model\StockValuationModelResponse;


class StockValuationPriceModelResponse implements StockValuationModelResponse
{
	public function __construct(
		private StockValuationModel $stockValuationModel,
		private StockAsset $stockAsset,
	) {
	}
}
