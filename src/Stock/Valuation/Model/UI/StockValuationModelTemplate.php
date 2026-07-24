<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\UI;

use App\UI\Base\BaseAdminPresenterTemplate;

class StockValuationModelTemplate extends BaseAdminPresenterTemplate
{

	public string|null $sortBy;

	public string $sortDirection;

}
