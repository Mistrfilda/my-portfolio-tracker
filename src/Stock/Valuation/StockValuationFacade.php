<?php

declare(strict_types = 1);

namespace App\Stock\Valuation;

use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Valuation\Data\StockValuationDataRepository;
use App\Stock\Valuation\Model\StockValuationModel;
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
	)
	{
	}

	public function getStockValuation(UuidInterface $stockAssetId): StockValuation
	{
		$stockAsset = $this->stockAssetRepository->getById($stockAssetId);
		$stockValuationData = $this->stockValuationDataRepository->findLatestForStockAsset($stockAsset);
		return new StockValuation($stockAsset, $stockValuationData);

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
		$stockValuationsModels = [];
		foreach ($this->models as $model) {
			$stockValuationsModels[] = $model->calculateResponse($stockValuations);
		}

		return $stockValuationsModels;
	}

}
