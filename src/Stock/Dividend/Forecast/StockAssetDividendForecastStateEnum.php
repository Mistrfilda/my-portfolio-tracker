<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Forecast;

enum StockAssetDividendForecastStateEnum: string
{

	case PENDING = 'pending';

	case RECALCULATING = 'recalculating';

	case RECALCULATING_IN_PROGRESS = 'recalculating_in_progress';

	case FINISHED = 'finished';

	public function format(): string
	{
		return match ($this) {
			StockAssetDividendForecastStateEnum::PENDING => 'Zpracovává se',
			StockAssetDividendForecastStateEnum::RECALCULATING => 'Připreveno na přepočítání',
			StockAssetDividendForecastStateEnum::RECALCULATING_IN_PROGRESS => 'Přepočítává se',
			StockAssetDividendForecastStateEnum::FINISHED => 'Vypočítáno',
		};
	}

}
