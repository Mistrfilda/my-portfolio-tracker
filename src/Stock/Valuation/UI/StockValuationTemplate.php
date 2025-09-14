<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\UI;

use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;
use App\Stock\Valuation\StockValuationTypeGroupEnum;
use App\UI\Base\BaseAdminPresenterTemplate;

class StockValuationTemplate extends BaseAdminPresenterTemplate
{

	/** @var array<StockValuationTypeGroupEnum> */
	public array $renderableTypeGroups;

	public string $currentTypeGroup;

	/** @var array<StockValuation> */
	public array $stockValuations;

	public StockValuationTypeGroupEnum $currentTypeGroupEnum;

	/** @var array<StockValuationTypeEnum> */
	public array $typesForGroup;

	public string|null $currentSortBy;

	public string $currentSortDirection;

}
