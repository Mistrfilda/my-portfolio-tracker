<?php

declare(strict_types = 1);

namespace App\Stock\Position\Closed\UI;

interface StockAssetClosedPositionListControlFactory
{

	public function create(): StockAssetClosedPositionListControl;

}
