<?php

declare(strict_types = 1);

namespace App\System\Resolver;

use App\Stock\Asset\StockAssetRepository;
use App\System\SystemValueEnum;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class SystemValueEnabledDividendStockAssetsResolver implements SystemValueResolver
{

	public function __construct(
		private StockAssetRepository $stockAssetRepository,
	)
	{

	}

	public function getValueForEnum(SystemValueEnum $systemValueEnum): string|int|ImmutableDateTime|null
	{
		return $this->stockAssetRepository->getDividendsEnabledCount();
	}

}
