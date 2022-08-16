<?php

declare(strict_types = 1);

namespace App\Stock\Asset\UI\Detail;

use App\Stock\Asset\StockAssetRepository;
use App\Stock\Position\StockPositionFacade;
use App\UI\Base\BaseControl;
use Ramsey\Uuid\UuidInterface;

class StockAssetDetailControl extends BaseControl
{

	/** @var array<UuidInterface> */
	private array $stockAssetIds;

	/**
	 * @param array<UuidInterface> $stockAssetsIds
	 */
	public function __construct(
		array $stockAssetsIds,
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
		foreach ($assets as $asset) {
			$stockAssetsPositionDTOs[] = $this->stockPositionFacade->getStockAssetDetailDTO($asset->getId());
		}

		$template = $this->getTemplate();
		$template->stockAssetsPositionDTOs = $stockAssetsPositionDTOs;
		$template->setFile(__DIR__ . '/templates/StockAssetDetailControl.latte');
		$template->render();
	}

}
