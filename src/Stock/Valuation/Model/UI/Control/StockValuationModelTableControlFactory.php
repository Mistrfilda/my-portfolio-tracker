<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\UI\Control;

interface StockValuationModelTableControlFactory
{

	public function create(): StockValuationModelTableControl;

}
