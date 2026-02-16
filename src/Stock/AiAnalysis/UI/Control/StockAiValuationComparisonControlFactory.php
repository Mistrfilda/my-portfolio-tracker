<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\UI\Control;

use Ramsey\Uuid\UuidInterface;

interface StockAiValuationComparisonControlFactory
{

	public function create(UuidInterface $stockAssetId): StockAiValuationComparisonControl;

}
