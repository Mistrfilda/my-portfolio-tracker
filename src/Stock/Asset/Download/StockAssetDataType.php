<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Download;

use App\Stock\Asset\StockAsset;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

enum StockAssetDataType: string
{

	case PRICE = 'priceDownloadedAt';
	case DIVIDENDS = 'dividendsCheckedAt';
	case VALUATION = 'valuationDownloadedAt';
	case ANALYST_INSIGHTS = 'analystInsightsDownloadedAt';

	public function getUpdatedAt(StockAsset $asset): ImmutableDateTime|null
	{
		return match ($this) {
			self::PRICE => $asset->getPriceDownloadedAt(),
			self::DIVIDENDS => $asset->getDividendsCheckedAt(),
			self::VALUATION => $asset->getValuationDownloadedAt(),
			self::ANALYST_INSIGHTS => $asset->getAnalystInsightsDownloadedAt(),
		};
	}

}
