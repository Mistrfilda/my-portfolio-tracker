<?php

declare(strict_types = 1);


namespace App\Stock\Valuation\Model\Price;


use App\Stock\Valuation\StockValuation;


interface StockValuationPriceModel
{
	public function calculateResponse(StockValuation $stockValuation): StockValuationPriceModelResponse;
}
