<?php

declare(strict_types = 1);

namespace App\System\Resolver;

use App\Stock\Asset\Download\StockAssetDataMonitoring;
use App\Stock\Asset\Download\StockAssetDataType;
use App\System\SystemValueEnum;
use InvalidArgumentException;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class SystemValueStockDataCountResolver implements SystemValueResolver
{

	public function __construct(
		private StockAssetDataMonitoring $monitoring,
		private SystemValueDatabaseResolver $legacy,
	)
	{
	}

	public function getValueForEnum(SystemValueEnum $systemValueEnum): string|int|ImmutableDateTime|null
	{
		if (!$this->monitoring->isEnabled()) {
			return $this->legacy->getValueForEnum($systemValueEnum);
		}

		return $this->monitoring->getFreshCount(match ($systemValueEnum) {
			SystemValueEnum::DIVIDENDS_UPDATED_COUNT => StockAssetDataType::DIVIDENDS,
			SystemValueEnum::STOCK_VALUATION_DOWNLOADED_COUNT => StockAssetDataType::VALUATION,
			SystemValueEnum::STOCK_VALUATION_ANALYST_INSIGHT_DOWNLOADED_COUNT => StockAssetDataType::ANALYST_INSIGHTS,
			default => throw new InvalidArgumentException('Unsupported stock data counter.'),
		});
	}

}
