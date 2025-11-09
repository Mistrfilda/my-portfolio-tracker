<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Forecast;

use App\UI\Control\Datagrid\Column\DatagridRenderableEnum;
use App\UI\Tailwind\TailwindColorConstant;

enum StockAssetDividendTrendEnum: string implements DatagridRenderableEnum
{

	case NEUTRAL = 'neutral';

	case OPTIMISTIC_15 = 'optimistic_15';

	case OPTIMISTIC_30 = 'optimistic_30';

	case PESSIMISTIC_15 = 'pessimistic_15';

	case PESSIMISTIC_30 = 'pessimistic_30';

	/**
	 * @return array<string, string>
	 */
	public static function getAdminSelectOptions(): array
	{
		return [
			StockAssetDividendTrendEnum::NEUTRAL->value => StockAssetDividendTrendEnum::NEUTRAL->format(),
			StockAssetDividendTrendEnum::OPTIMISTIC_15->value => StockAssetDividendTrendEnum::OPTIMISTIC_15->format(),
			StockAssetDividendTrendEnum::OPTIMISTIC_30->value => StockAssetDividendTrendEnum::OPTIMISTIC_30->format(),
			StockAssetDividendTrendEnum::PESSIMISTIC_15->value => StockAssetDividendTrendEnum::PESSIMISTIC_15->format(),
			StockAssetDividendTrendEnum::PESSIMISTIC_30->value => StockAssetDividendTrendEnum::PESSIMISTIC_30->format(),
		];
	}

	public function getTrendNumber(): int
	{
		return match ($this) {
			StockAssetDividendTrendEnum::NEUTRAL => 0,
			StockAssetDividendTrendEnum::OPTIMISTIC_15 => 15,
			StockAssetDividendTrendEnum::OPTIMISTIC_30 => 30,
			StockAssetDividendTrendEnum::PESSIMISTIC_15 => -15,
			StockAssetDividendTrendEnum::PESSIMISTIC_30 => -30,
		};
	}

	public function getTrendColor(): string
	{
		return match ($this) {
			StockAssetDividendTrendEnum::NEUTRAL => TailwindColorConstant::EMERALD,
			StockAssetDividendTrendEnum::OPTIMISTIC_15, StockAssetDividendTrendEnum::OPTIMISTIC_30 => TailwindColorConstant::GREEN,
			StockAssetDividendTrendEnum::PESSIMISTIC_15, StockAssetDividendTrendEnum::PESSIMISTIC_30 => TailwindColorConstant::RED,
		};
	}

	public function format(): string
	{
		return match ($this) {
			StockAssetDividendTrendEnum::NEUTRAL => 'Neutrální',
			StockAssetDividendTrendEnum::OPTIMISTIC_15 => 'Optimistický 15%',
			StockAssetDividendTrendEnum::OPTIMISTIC_30 => 'Optimistický 30%',
			StockAssetDividendTrendEnum::PESSIMISTIC_15 => 'Pesimistický -15%',
			StockAssetDividendTrendEnum::PESSIMISTIC_30 => 'Pesimistický -30%',
		};
	}

}
