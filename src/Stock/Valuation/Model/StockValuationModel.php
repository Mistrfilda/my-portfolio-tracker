<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model;

use App\Stock\Valuation\Model\Price\StockValuationPriceModelResponse;
use App\Stock\Valuation\StockValuation;

interface StockValuationModel
{

	public function calculateResponse(StockValuation $stockValuation): StockValuationPriceModelResponse;

}
