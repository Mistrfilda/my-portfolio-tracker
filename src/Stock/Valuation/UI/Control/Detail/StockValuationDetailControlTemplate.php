<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\UI\Control\Detail;

use App\Asset\Price\AssetPrice;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Data\StockValuationData;
use App\Stock\Valuation\Model\StockValuationModelResponse;
use App\Stock\Valuation\StockValuation;
use App\UI\Base\BaseControlTemplate;

class StockValuationDetailControlTemplate extends BaseControlTemplate
{

	public StockAsset $stockAsset;

	public StockValuation $stockValuation;

	/** @var array<StockValuationModelResponse> */
	public array $stockValuationModelResponses;

	/** @var array<StockValuationData> */
	public array $stockValuationAnalyticsPrices;

	public AssetPrice $averagePrice;

}
