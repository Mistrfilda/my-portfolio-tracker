<?php

declare(strict_types = 1);

namespace App\Stock\Asset\UI\Detail\List;

use App\Stock\Asset\StockAssetDetailDTO;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\UI\Detail\StockDividendDetailControl;
use App\Stock\Dividend\UI\StockAssetDividendDetailService;
use App\Stock\Position\StockPositionFacade;
use App\UI\Base\BaseControl;
use Mistrfilda\Datetime\DatetimeFactory;
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
		private readonly StockAssetDividendDetailService $stockAssetDividendDetailService,
		private readonly DatetimeFactory $datetimeFactory,
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
		$lastDaysDividendDetailDTOs = [];
		$totalInvestedAmountInCzk = 0;
		$lastDaysDividendFrom = $this->datetimeFactory->createNow()->deductDaysFromDatetime(
			StockDividendDetailControl::DAYS_SINCE,
		);
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
			$lastDaysDividendDetailDTOs[$asset->getId()->toString()] = $this->stockAssetDividendDetailService->getDetailDTOFromDate(
				$asset,
				$lastDaysDividendFrom,
			);
			$totalInvestedAmountInCzk += $detailDTO->getCurrentPriceInCzk()->getPrice();
		}

		$template = $this->createTemplate(StockAssetListDetailControlTemplate::class);
		assert($template instanceof StockAssetListDetailControlTemplate);
		$template->stockAssetsPositionDTOs = $stockAssetsPositionDTOs;

		$sortedStockAssetsPositionsDTOs = $stockAssetsPositionDTOs;
		usort($sortedStockAssetsPositionsDTOs, static function (StockAssetDetailDTO $a, StockAssetDetailDTO $b): int {
			if ($a->getCurrentPriceInCzk()->getPrice() > $b->getCurrentPriceInCzk()->getPrice()) {
				return -1;
			}

			return 1;
		});

		$template->assetDetailControlEnum = $this->assetDetailControlEnum;
		$template->lastDaysDividendDetailDTOs = $lastDaysDividendDetailDTOs;
		$template->totalInvestedAmountInCzk = $totalInvestedAmountInCzk;
		$template->sortedStockAssetsPositionsDTOs = $sortedStockAssetsPositionsDTOs;
		$template->setFile(__DIR__ . '/templates/StockAssetListDetailControl.latte');
		$template->render();
	}

}
