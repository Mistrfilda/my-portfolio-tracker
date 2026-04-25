<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\UI;

use App\UI\Base\BaseAdminPresenterTemplate;

class StockValuationOverviewTemplate extends BaseAdminPresenterTemplate
{

	/** @var array<StockValuationOverviewRow> */
	public array $rows;

}
