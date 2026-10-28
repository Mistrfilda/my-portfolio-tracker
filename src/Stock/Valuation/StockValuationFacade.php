<?php

declare(strict_types = 1);

namespace App\Stock\Valuation;

use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Valuation\Data\StockValuationDataRepository;
use App\Stock\Valuation\Model\StockValuationModel;
use App\Stock\Valuation\Model\StockValuationModelEvaluator;
use App\Stock\Valuation\Model\StockValuationModelResponse;
use Ramsey\Uuid\UuidInterface;

class StockValuationFacade
{

	/**
	 * @param array<StockValuationModel> $models
	 */
	public function __construct(
		private array $models,
		private StockAssetRepository $stockAssetRepository,
		private StockValuationDataRepository $stockValuationDataRepository,
		private StockValuationModelEvaluator $modelEvaluator,
	)
	{
	}

	public function getStockValuation(UuidInterface $stockAssetId): StockValuation
	{
		$stockAsset = $this->stockAssetRepository->getById($stockAssetId);
		$stockValuationData = $this->stockValuationDataRepository->findLatestForStockAsset($stockAsset);
		$valuation = new StockValuation($stockAsset, $stockValuationData);
		$importedAt = $valuation->getImportedAt();
		$previous = $importedAt === null
			? []
			: $this->stockValuationDataRepository->findPreviousForStockAsset(
				$stockAsset,
				$importedAt,
			);
		return new StockValuation($stockAsset, $stockValuationData, $previous);

	}

	/**
	 * @return array<StockValuation>
	 */
	public function getStockValuations(): array
	{
		$stockAssets = $this->stockAssetRepository->getAllActiveValuationAssets();

		$stockValuations = [];
		foreach ($stockAssets as $stockAsset) {
			$stockValuations[] = $this->getStockValuation($stockAsset->getId());
		}

		return $stockValuations;
	}

	/**
	 * @return array<array<StockValuationModelResponse>>
	 */
	public function getAllStockAssetsValuationsModels(): array
	{
		$stockAssets = $this->stockAssetRepository->getAllActiveValuationAssets();
		$stockValuations = [];
		foreach ($stockAssets as $stockAsset) {
			$stockValuations[] = $this->getStockValuationsModelsForStockAsset($stockAsset);
		}

		return $stockValuations;
	}

	/**
	 * @return array<StockValuationModelResponse>
	 */
	public function getStockValuationsModelsForStockAsset(StockAsset $stockAsset): array
	{
		$stockValuations = $this->getStockValuation($stockAsset->getId());
		return $this->getModelsForValuation($stockValuations);
	}

	/** @return array<StockValuationModelResponse> */
	public function getModelsForValuation(StockValuation $stockValuations): array
	{
		$stockValuationsModels = [];
		foreach ($this->models as $model) {
			$stockValuationsModels[] = $this->modelEvaluator->evaluate($model, $stockValuations);
		}

		return $stockValuationsModels;
	}

}
