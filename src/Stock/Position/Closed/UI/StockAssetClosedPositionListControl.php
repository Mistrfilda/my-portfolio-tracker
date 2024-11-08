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

			return $bPriceDiff <=> $aPriceDiff;
		});

		$this->template->positions = $positions;
		$this->getTemplate()->setFile(__DIR__ . '/StockAssetClosedPositionListControl.latte');
		$this->getTemplate()->render();
	}

}
