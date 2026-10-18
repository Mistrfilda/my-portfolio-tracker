<?php

declare(strict_types = 1);

namespace App\Crypto\Position\Closed\UI;

use App\Crypto\Position\Closed\CryptoAssetClossedPositionDTO;
use App\UI\Base\BaseControlTemplate;

class CryptoAssetClosedPositionListControlTemplate extends BaseControlTemplate
{

	/** @var array<CryptoAssetClossedPositionDTO> */
	public array $positions;

}
