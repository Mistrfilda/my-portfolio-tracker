<?php

declare(strict_types = 1);

namespace App\Crypto\Position;

use App\Asset\Price\PriceDiff;

class CryptoAssetPositionDetailDTO
{

	public function __construct(
		private readonly CryptoPosition $cryptoPosition,
		private readonly PriceDiff $priceDiff,
	)
	{
	}

	public function getCryptoPosition(): CryptoPosition
	{
		return $this->cryptoPosition;
	}

	public function getPriceDiff(): PriceDiff
	{
		return $this->priceDiff;
	}

}
