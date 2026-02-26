<?php

declare(strict_types = 1);

namespace App\Stock\Position\Closed\UI;

use App\Stock\Position\Closed\StockAssetClossedPositionDTO;
use App\Stock\Position\Closed\StockClosedPositionFacade;
use App\UI\Base\BaseControl;

class StockAssetClosedPositionListControl extends BaseControl
{

	public function __construct(
		private readonly StockClosedPositionFacade $stockClosedPositionFacade,
	)
	{
	}

	public function render(): void
	{
		$positions = $this->stockClosedPositionFacade->getAllStockClosedPositions();
		usort($positions, static function (StockAssetClossedPositionDTO $a, StockAssetClossedPositionDTO $b): int {
			$aPriceDiff = $a->getTotalAmountPriceDiffInBrokerCurrencyWithDividends() ?? $a->getTotalAmountPriceDiffInBrokerCurrency();

			$bPriceDiff = $b->getTotalAmountPriceDiffInBrokerCurrencyWithDividends() ?? $b->getTotalAmountPriceDiffInBrokerCurrency();

			return $bPriceDiff->getPriceDifference() <=> $aPriceDiff->getPriceDifference();
		});

		$totalPriceDiffInCzk = $this->stockClosedPositionFacade->getAllStockClosedPositionsSummaryPrice();

		$template = $this->getTemplate();
		$template->positions = $positions;
		$template->totalPriceDiffInCzk = $totalPriceDiffInCzk;
		$template->setFile(__DIR__ . '/StockAssetClosedPositionListControl.latte');
		$template->render();
	}

}
