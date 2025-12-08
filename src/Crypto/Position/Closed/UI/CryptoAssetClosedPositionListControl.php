<?php

declare(strict_types = 1);

namespace App\Crypto\Position\Closed\UI;

use App\Crypto\Position\Closed\CryptoAssetClossedPositionDTO;
use App\Crypto\Position\Closed\CryptoClosedPositionFacade;
use App\UI\Base\BaseControl;

class CryptoAssetClosedPositionListControl extends BaseControl
{

	public function __construct(
		private readonly CryptoClosedPositionFacade $cryptoClosedPositionFacade,
	)
	{
	}

	public function render(): void
	{
		$positions = $this->cryptoClosedPositionFacade->getAllCryptoClosedPositions();
		usort($positions, static function (CryptoAssetClossedPositionDTO $a, CryptoAssetClossedPositionDTO $b): int {
			$aPriceDiff = $a->getTotalAmountPriceDiffInBrokerCurrency();

			$bPriceDiff = $b->getTotalAmountPriceDiffInBrokerCurrency();

			return $bPriceDiff <=> $aPriceDiff;
		});

		$this->template->positions = $positions;
		$this->getTemplate()->setFile(__DIR__ . '/CryptoAssetClosedPositionListControl.latte');
		$this->getTemplate()->render();
	}

}
