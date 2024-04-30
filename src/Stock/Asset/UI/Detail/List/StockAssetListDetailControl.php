<?php

declare(strict_types = 1);

namespace App\Stock\Asset\UI\Detail\List;

use App\Stock\Asset\StockAssetDetailDTO;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Position\StockPositionFacade;
use App\UI\Base\BaseControl;
use Ramsey\Uuid\UuidInterface;

class StockAssetListDetailControl extends BaseControl
{

	/** @var array<UuidInterface> */
	private array $stockAssetIds;

	/**
	 * @param array<UuidInterface> $stockAssetsIds
	 */
	public function __construct(
		array $stockAssetsIds,
		private StockAssetListDetailControlEnum $assetDetailControlEnum,
		private readonly StockAssetRepository $stockAssetRepository,
		private readonly StockPositionFacade $stockPositionFacade,
	)
	{
		$this->stockAssetIds = $stockAssetsIds;
	}

	public function render(): void
	{
		$assets = count($this->stockAssetIds) === 0
			? $this->stockAssetRepository->findAll()
			: $this->stockAssetRepository->findByIds($this->stockAssetIds);

		$stockAssetsPositionDTOs = [];
		$totalInvestedAmountInCzk = 0;
		foreach ($assets as $asset) {
			if ($asset->hasOpenPositions()) {
				if ($this->assetDetailControlEnum === StockAssetListDetailControlEnum::CLOSED_POSITIONS) {
					continue;
				}
			} else {
				if ($this->assetDetailControlEnum === StockAssetListDetailControlEnum::OPEN_POSITIONS) {
					continue;
				}
			}

			$detailDTO = $this->stockPositionFacade->getStockAssetDetailDTO(
				$asset->getId(),
				$this->assetDetailControlEnum,
			);

			$stockAssetsPositionDTOs[] = $detailDTO;
			$totalInvestedAmountInCzk += $detailDTO->getCurrentPriceInCzk()->getPrice();
		}

		$template = $this->getTemplate();
		$template->stockAssetsPositionDTOs = $stockAssetsPositionDTOs;

		$sortedStockAssetsPositionsDTOs = $stockAssetsPositionDTOs;
		usort($sortedStockAssetsPositionsDTOs, static function (StockAssetDetailDTO $a, StockAssetDetailDTO $b): int {
			if ($a->getCurrentPriceInCzk()->getPrice() > $b->getCurrentPriceInCzk()->getPrice()) {
				return -1;
			}

			return 1;
		});

		$template->assetDetailControlEnum = $this->assetDetailControlEnum;
		$template->totalInvestedAmountInCzk = $totalInvestedAmountInCzk;
		$template->sortedStockAssetsPositionsDTOs = $sortedStockAssetsPositionsDTOs;
		$template->setFile(__DIR__ . '/templates/StockAssetListDetailControl.latte');
		$template->render();
	}

}
