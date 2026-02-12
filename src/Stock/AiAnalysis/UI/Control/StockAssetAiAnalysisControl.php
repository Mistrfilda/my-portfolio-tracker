<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\UI\Control;

use App\Stock\AiAnalysis\StockAiAnalysisStockResultRepository;
use App\Stock\Asset\StockAssetRepository;
use App\UI\Base\BaseControl;
use Ramsey\Uuid\UuidInterface;
use function assert;

class StockAssetAiAnalysisControl extends BaseControl
{

	public function __construct(
		private UuidInterface $stockAssetId,
		private StockAiAnalysisStockResultRepository $stockAiAnalysisStockResultRepository,
		private StockAssetRepository $stockAssetRepository,
	)
	{
	}

	public function render(): void
	{
		$template = $this->createTemplate(StockAssetAiAnalysisControlTemplate::class);
		assert($template instanceof StockAssetAiAnalysisControlTemplate);

		$stockAsset = $this->stockAssetRepository->getById($this->stockAssetId);
		$template->results = $this->stockAiAnalysisStockResultRepository->findLatestForStockAsset($stockAsset, 5);
		$template->setFile(__DIR__ . '/StockAssetAiAnalysisControl.latte');
		$template->render();
	}

}
