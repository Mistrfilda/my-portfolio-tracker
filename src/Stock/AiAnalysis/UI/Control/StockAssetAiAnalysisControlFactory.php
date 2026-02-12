<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\UI\Control;

use Ramsey\Uuid\UuidInterface;

interface StockAssetAiAnalysisControlFactory
{

	public function create(UuidInterface $stockAssetId): StockAssetAiAnalysisControl;

}
