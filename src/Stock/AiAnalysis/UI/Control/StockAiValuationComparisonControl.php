<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\UI\Control;

use App\Stock\AiAnalysis\StockAiAnalysisStockResultRepository;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Valuation\Model\Consensus\StockValuationModelConsensusProvider;
use App\Stock\Valuation\StockValuationFacade;
use App\UI\Base\BaseControl;
use Ramsey\Uuid\UuidInterface;

class StockAiValuationComparisonControl extends BaseControl
{

	public function __construct(
		private UuidInterface $stockAssetId,
		private StockValuationFacade $stockValuationFacade,
		private StockValuationModelConsensusProvider $stockValuationModelConsensusProvider,
		private StockAssetRepository $stockAssetRepository,
		private StockAiAnalysisStockResultRepository $stockAiAnalysisStockResultRepository,
	)
	{
	}

	public function render(): void
	{
		$template = $this->createTemplate(StockAiValuationComparisonControlTemplate::class);
		assert($template instanceof StockAiValuationComparisonControlTemplate);

		$stockAsset = $this->stockAssetRepository->getById($this->stockAssetId);
		$template->stockAsset = $stockAsset;

		$modelResponses = $this->stockValuationFacade->getStockValuationsModelsForStockAsset(
			$stockAsset,
		);
		$template->stockValuationModelResponses = $modelResponses;
		$template->modelConsensus = $this->stockValuationModelConsensusProvider->getForStockAsset(
			$stockAsset,
			$modelResponses,
		);

		$aiResults = $this->stockAiAnalysisStockResultRepository->findLatestForStockAsset($stockAsset, 1);
		$template->aiResult = count($aiResults) > 0 ? $aiResults[0] : null;

		$template->setFile(__DIR__ . '/StockAiValuationComparisonControl.latte');
		$template->render();
	}

}
