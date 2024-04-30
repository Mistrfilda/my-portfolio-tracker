<?php

declare(strict_types = 1);

namespace App\Stock\Asset\UI\Detail;

use App\Stock\Asset\StockAssetRepository;
use App\Stock\Asset\UI\Detail\List\StockAssetListDetailControlEnum;
use App\Stock\Position\StockPositionFacade;
use App\UI\Base\BaseControl;
use Ramsey\Uuid\UuidInterface;
use function assert;

/**
 * @property-read StockAssetDetailControlTemplate $template
 */
class StockAssetDetailControl extends BaseControl
{

	public function __construct(
		private UuidInterface $id,
		private StockAssetRepository $stockAssetRepository,
		private StockPositionFacade $stockPositionFacade,
	)
	{
	}

	public function render(): void
	{
		$template = $this->getTemplate();
		assert($template instanceof StockAssetDetailControlTemplate);

		$template->stockAsset = $this->stockAssetRepository->getById($this->id);
		$template->openStockAssetDetailDTO = $this->stockPositionFacade->getStockAssetDetailDTO($this->id);
		$template->closedStockAssetDetailDTO = $this->stockPositionFacade->getStockAssetDetailDTO(
			$this->id,
			StockAssetListDetailControlEnum::CLOSED_POSITIONS,
		);
		$template->setFile(__DIR__ . '/templates/StockAssetDetailControl.latte');
		$template->render();
	}

}
