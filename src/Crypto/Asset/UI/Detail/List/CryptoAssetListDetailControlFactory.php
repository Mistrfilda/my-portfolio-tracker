<?php

declare(strict_types = 1);

namespace App\Crypto\Asset\UI\Detail\List;

use Ramsey\Uuid\UuidInterface;

interface CryptoAssetListDetailControlFactory
{

	/**
	 * @param array<UuidInterface> $cryptoAssetsIds
	 */
	public function create(
		array $cryptoAssetsIds,
		CryptoAssetListDetailControlEnum $assetDetailControlEnum,
	): CryptoAssetListDetailControl;

}
