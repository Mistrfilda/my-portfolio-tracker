<?php

declare(strict_types = 1);

namespace App\Asset;

interface AssetRepository
{

	/** @return array<Asset> */
	public function getAllActiveAssets(): array;

}
