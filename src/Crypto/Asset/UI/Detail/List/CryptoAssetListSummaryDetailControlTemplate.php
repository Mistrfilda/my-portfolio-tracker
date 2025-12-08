<?php

declare(strict_types = 1);

namespace App\Crypto\Asset\UI\Detail\List;

use App\Crypto\Asset\CryptoAssetDetailDTO;
use App\UI\Base\BaseControlTemplate;

class CryptoAssetListSummaryDetailControlTemplate extends BaseControlTemplate
{

	/** @var array<CryptoAssetDetailDTO> */
	public array $sortedCryptoAssetsPositionsDTOs;

	public float $totalInvestedAmountInCzk;

}
