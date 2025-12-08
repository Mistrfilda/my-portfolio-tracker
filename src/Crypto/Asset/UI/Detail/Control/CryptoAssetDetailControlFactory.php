<?php

declare(strict_types = 1);

namespace App\Crypto\Asset\UI\Detail\Control;

use Ramsey\Uuid\UuidInterface;

interface CryptoAssetDetailControlFactory
{

	public function create(UuidInterface $id, int $currentChartDays): CryptoAssetDetailControl;

}
