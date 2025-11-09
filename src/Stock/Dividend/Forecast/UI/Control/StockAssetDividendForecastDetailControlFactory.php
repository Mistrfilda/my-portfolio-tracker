<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Forecast\UI\Control;

use Ramsey\Uuid\UuidInterface;

interface StockAssetDividendForecastDetailControlFactory
{

	public function create(UuidInterface $forecastId): StockAssetDividendForecastDetailControl;

}
