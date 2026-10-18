<?php

declare(strict_types = 1);

namespace App\Crypto\Position\Closed\UI;

use App\Crypto\Position\Closed\CryptoAssetClossedPositionDTO;
use App\Crypto\Position\Closed\CryptoClosedPositionFacade;
use App\UI\Base\BaseControl;

/** @property-read CryptoAssetClosedPositionListControlTemplate $template */
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

		$template = $this->createTemplate(CryptoAssetClosedPositionListControlTemplate::class);
		assert($template instanceof CryptoAssetClosedPositionListControlTemplate);
		$template->positions = $positions;
		$template->setFile(__DIR__ . '/CryptoAssetClosedPositionListControl.latte');
		$template->render();
	}

}
