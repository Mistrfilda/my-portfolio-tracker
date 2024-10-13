<?php

declare(strict_types = 1);

namespace App\Asset\Price;

interface AssetPriceSourceProvider
{

	public function generatePriceSourcesJsonFile(string $fileLocation): void;

}
