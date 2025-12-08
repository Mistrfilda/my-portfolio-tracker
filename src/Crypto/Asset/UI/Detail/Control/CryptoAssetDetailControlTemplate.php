<?php

declare(strict_types = 1);

namespace App\Crypto\Asset\UI\Detail\Control;

use App\Crypto\Asset\CryptoAsset;
use App\Crypto\Asset\CryptoAssetDetailDTO;
use App\UI\Base\BaseControlTemplate;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\SmartObject;

class CryptoAssetDetailControlTemplate extends BaseControlTemplate
{

	use SmartObject;

	public CryptoAsset $cryptoAsset;

	public CryptoAssetDetailDTO $openCryptoAssetDetailDTO;

	public CryptoAssetDetailDTO $closedCryptoAssetDetailDTO;

	public ImmutableDateTime $now;

	/** @var array<int, string> */
	public array $chartOptions;

	public int $currentChartDays;

}
