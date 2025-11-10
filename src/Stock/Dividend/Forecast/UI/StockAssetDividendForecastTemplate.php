<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Forecast\UI;

use App\Stock\Dividend\Forecast\StockAssetDividendForecast;
use App\UI\Base\BaseAdminPresenterTemplate;

class StockAssetDividendForecastTemplate extends BaseAdminPresenterTemplate
{

	/** @var array<int, array<int, StockAssetDividendForecast>> */
	public array $stockAssetDividendForecastsByYear;

}
