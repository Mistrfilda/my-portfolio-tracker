<?php

declare(strict_types = 1);

namespace App\Crypto\Position\Closed\UI;

interface CryptoAssetClosedPositionListControlFactory
{

	public function create(): CryptoAssetClosedPositionListControl;

}
