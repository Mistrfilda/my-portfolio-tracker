<?php

declare(strict_types = 1);

namespace App\Stock\Valuation;

use App\Stock\Asset\StockAssetRepository;
use App\Stock\Valuation\Data\StockValuationDataRepository;
use Ramsey\Uuid\UuidInterface;

class StockValuationFacade
{

	public function __construct(
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

}
